<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\BankAccount;
use App\Models\User;
use App\Repositories\Contracts\BankAccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class BankAccountService
{
    public function __construct(
        protected BankAccountRepositoryInterface $bankAccounts,
        protected NotificationService $notificationService,
    ) {
    }

    public function listForUser(User $user): Collection
    {
        return $this->bankAccounts->allForUser($user);
    }

    public function add(User $user, array $data): BankAccount
    {
        $hash = BankAccount::hashAccountNumber($data['account_number']);

        if ($this->bankAccounts->existsForUser($user, $hash)) {
            throw new ApiException('This bank account has already been added.', 422);
        }

        $isFirstAccount = $this->bankAccounts->allForUser($user)->isEmpty();
        $makePrimary = $isFirstAccount || ($data['is_primary'] ?? false);

        return DB::transaction(function () use ($user, $data, $hash, $makePrimary) {
            if ($makePrimary) {
                $this->bankAccounts->unsetPrimaryForUser($user);
            }

            return $this->bankAccounts->create([
                'user_id' => $user->id,
                'bank_name' => $data['bank_name'],
                'account_holder_name' => $data['account_holder_name'],
                'account_number_encrypted' => Crypt::encryptString($data['account_number']),
                'account_number_hash' => $hash,
                'account_number_last4' => substr($data['account_number'], -4),
                'ifsc_code' => strtoupper($data['ifsc_code']),
                'account_type' => $data['account_type'] ?? 'savings',
                'is_primary' => $makePrimary,
                'is_verified' => false,
            ]);
        });
    }

    public function update(User $user, BankAccount $bankAccount, array $data): BankAccount
    {
        $this->assertOwnership($user, $bankAccount);

        $payload = array_filter([
            'bank_name' => $data['bank_name'] ?? null,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'account_type' => $data['account_type'] ?? null,
        ], fn ($value) => ! is_null($value));

        if (! empty($payload)) {
            $this->bankAccounts->update($bankAccount, $payload);
        }

        if (! empty($data['is_primary'])) {
            $this->setPrimary($user, $bankAccount);
        }

        return $bankAccount->fresh();
    }

    public function setPrimary(User $user, BankAccount $bankAccount): BankAccount
    {
        $this->assertOwnership($user, $bankAccount);

        return DB::transaction(function () use ($user, $bankAccount) {
            $this->bankAccounts->unsetPrimaryForUser($user, $bankAccount->id);
            $this->bankAccounts->update($bankAccount, ['is_primary' => true]);

            return $bankAccount->fresh();
        });
    }

    public function delete(User $user, BankAccount $bankAccount): void
    {
        $this->assertOwnership($user, $bankAccount);

        DB::transaction(function () use ($user, $bankAccount) {
            $wasPrimary = $bankAccount->is_primary;

            $this->bankAccounts->delete($bankAccount);

            if ($wasPrimary) {
                $next = $this->bankAccounts->allForUser($user)->first();

                if ($next) {
                    $this->bankAccounts->update($next, ['is_primary' => true]);
                }
            }
        });
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->bankAccounts->paginateAll($filters, $perPage);
    }

    public function verify(BankAccount $bankAccount): BankAccount
    {
        $bankAccount = $this->bankAccounts->update($bankAccount, [
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        $this->notificationService->send(
            $bankAccount->user,
            'Bank account verified',
            "Your {$bankAccount->bank_name} account is now verified for withdrawals.",
            'bank_verified'
        );

        return $bankAccount;
    }

    protected function assertOwnership(User $user, BankAccount $bankAccount): void
    {
        if ($bankAccount->user_id !== $user->id) {
            throw new ApiException('This bank account does not belong to your account.', 403);
        }
    }
}
