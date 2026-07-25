<?php

namespace App\Repositories\Contracts;

use App\Models\Account;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface AccountRepositoryInterface extends BaseRepositoryInterface
{
    public function findByUser(User $user): ?Account;

    public function generateUniqueAccountNumber(): string;

    public function lockForUpdate(int $accountId): Account;

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator;
}
