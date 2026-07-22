<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Pagination\LengthAwarePaginator;

interface WalletRepositoryInterface extends BaseRepositoryInterface
{
    public function findByUser(User $user): ?Wallet;

    public function findByWalletNumber(string $walletNumber): ?Wallet;

    public function generateUniqueWalletNumber(): string;

    public function lockForUpdate(int $walletId): Wallet;

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator;
}
