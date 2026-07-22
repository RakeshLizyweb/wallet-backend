<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BankAccountRepositoryInterface extends BaseRepositoryInterface
{
    public function allForUser(User $user): Collection;

    public function unsetPrimaryForUser(User $user, ?int $exceptId = null): void;

    public function existsForUser(User $user, string $accountNumberHash): bool;

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator;
}
