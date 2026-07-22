<?php

namespace App\Services;

use App\Enums\LedgerCategory;
use App\Enums\LedgerType;
use App\Enums\WalletStatus;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Repositories\Contracts\WalletTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletService
{
    public function __construct(
        protected WalletRepositoryInterface $wallets,
        protected WalletTransactionRepositoryInterface $ledger,
    ) {
    }

    public function createForUser(User $user): Wallet
    {
        return $this->wallets->create([
            'user_id' => $user->id,
            'wallet_number' => $this->wallets->generateUniqueWalletNumber(),
            'currency' => config('wallet.currency'),
            'status' => WalletStatus::Active->value,
        ]);
    }

    public function getForUser(User $user): Wallet
    {
        $wallet = $this->wallets->findByUser($user);

        if (! $wallet) {
            throw new ApiException('Wallet not found for this account.', 404);
        }

        return $wallet;
    }

    public function credit(
        Wallet $wallet,
        float $amount,
        LedgerCategory $category,
        ?string $referenceNumber = null,
        ?Model $source = null,
        ?string $description = null
    ): WalletTransaction {
        return DB::transaction(function () use ($wallet, $amount, $category, $referenceNumber, $source, $description) {
            $locked = $this->wallets->lockForUpdate($wallet->id);

            $balanceBefore = (float) $locked->balance;
            $balanceAfter = round($balanceBefore + $amount, 2);

            $locked->update(['balance' => $balanceAfter]);

            return $this->ledger->create([
                'wallet_id' => $locked->id,
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
        Wallet $wallet,
        float $amount,
        LedgerCategory $category,
        ?string $referenceNumber = null,
        ?Model $source = null,
        ?string $description = null
    ): WalletTransaction {
        return DB::transaction(function () use ($wallet, $amount, $category, $referenceNumber, $source, $description) {
            $locked = $this->wallets->lockForUpdate($wallet->id);

            if (! $locked->isActive()) {
                throw new ApiException('Wallet is not active.', 422);
            }

            $available = (float) $locked->balance - (float) $locked->frozen_balance;

            if ($available < $amount) {
                throw new ApiException('Insufficient wallet balance.', 422);
            }

            $balanceBefore = (float) $locked->balance;
            $balanceAfter = round($balanceBefore - $amount, 2);

            $locked->update(['balance' => $balanceAfter]);

            return $this->ledger->create([
                'wallet_id' => $locked->id,
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

    public function miniStatement(Wallet $wallet, int $limit = 10): Collection
    {
        return $this->ledger->miniStatement($wallet, $limit);
    }

    public function fullStatement(Wallet $wallet, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->ledger->fullStatement($wallet, $filters, $perPage);
    }

    public function generateReferenceNumber(): string
    {
        return 'TXN'.now()->format('YmdHis').Str::upper(Str::random(4));
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->wallets->paginateAll($filters, $perPage);
    }

    public function setStatus(Wallet $wallet, WalletStatus $status): Wallet
    {
        return $this->wallets->update($wallet, ['status' => $status->value]);
    }

    public function adjust(Wallet $wallet, float $amount, LedgerType $type, string $reason): WalletTransaction
    {
        $description = "Admin adjustment: {$reason}";

        return $type === LedgerType::Credit
            ? $this->credit($wallet, $amount, LedgerCategory::Adjustment, null, null, $description)
            : $this->debit($wallet, $amount, LedgerCategory::Adjustment, null, null, $description);
    }
}
