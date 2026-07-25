<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\LedgerCategory;
use App\Enums\LedgerType;
use App\Exceptions\ApiException;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\User;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\AccountTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountService
{
    public function __construct(
        protected AccountRepositoryInterface $accounts,
        protected AccountTransactionRepositoryInterface $ledger,
    ) {
    }

    public function createForUser(User $user): Account
    {
        return $this->accounts->create([
            'user_id' => $user->id,
            'account_number' => $this->accounts->generateUniqueAccountNumber(),
            'currency' => config('wallet.currency'),
            'status' => AccountStatus::Active->value,
        ]);
    }

    public function getForUser(User $user): Account
    {
        $account = $this->accounts->findByUser($user);

        if (! $account) {
            throw new ApiException('Account not found for this user.', 404);
        }

        return $account;
    }

    public function credit(
        Account $account,
        float $amount,
        LedgerCategory $category,
        ?string $referenceNumber = null,
        ?Model $source = null,
        ?string $description = null
    ): AccountTransaction {
        return DB::transaction(function () use ($account, $amount, $category, $referenceNumber, $source, $description) {
            $locked = $this->accounts->lockForUpdate($account->id);

            $balanceBefore = (float) $locked->balance;
            $balanceAfter = round($balanceBefore + $amount, 2);

            $locked->update(['balance' => $balanceAfter]);

            return $this->ledger->create([
                'account_id' => $locked->id,
                'user_id' => $locked->user_id,
                'reference_number' => $referenceNumber ?? $this->generateReferenceNumber(),
                'type' => LedgerType::Credit->value,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'category' => $category->value,
                'description' => $description,
                'sourceable_type' => $source?->getMorphClass(),
                'sourceable_id' => $source?->getKey(),
            ]);
        });
    }

    public function debit(
        Account $account,
        float $amount,
        LedgerCategory $category,
        ?string $referenceNumber = null,
        ?Model $source = null,
        ?string $description = null
    ): AccountTransaction {
        return DB::transaction(function () use ($account, $amount, $category, $referenceNumber, $source, $description) {
            $locked = $this->accounts->lockForUpdate($account->id);

            if (! $locked->isActive()) {
                throw new ApiException('Account is not active.', 422);
            }

            $available = (float) $locked->balance - (float) $locked->frozen_balance;

            if ($available < $amount) {
                throw new ApiException('Insufficient account balance.', 422);
            }

            $balanceBefore = (float) $locked->balance;
            $balanceAfter = round($balanceBefore - $amount, 2);

            $locked->update(['balance' => $balanceAfter]);

            return $this->ledger->create([
                'account_id' => $locked->id,
                'user_id' => $locked->user_id,
                'reference_number' => $referenceNumber ?? $this->generateReferenceNumber(),
                'type' => LedgerType::Debit->value,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'category' => $category->value,
                'description' => $description,
                'sourceable_type' => $source?->getMorphClass(),
                'sourceable_id' => $source?->getKey(),
            ]);
        });
    }

    public function miniStatement(Account $account, int $limit = 10): Collection
    {
        return $this->ledger->miniStatement($account, $limit);
    }

    public function fullStatement(Account $account, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->ledger->fullStatement($account, $filters, $perPage);
    }

    public function generateReferenceNumber(): string
    {
        return 'ATX'.now()->format('YmdHis').Str::upper(Str::random(4));
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->accounts->paginateAll($filters, $perPage);
    }

    public function setStatus(Account $account, AccountStatus $status): Account
    {
        return $this->accounts->update($account, ['status' => $status->value]);
    }

    public function adjust(Account $account, float $amount, LedgerType $type, string $reason): AccountTransaction
    {
        $description = "Admin adjustment: {$reason}";

        return $type === LedgerType::Credit
            ? $this->credit($account, $amount, LedgerCategory::Adjustment, null, null, $description)
            : $this->debit($account, $amount, LedgerCategory::Adjustment, null, null, $description);
    }
}
