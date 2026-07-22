<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface ScratchCardRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateForUser(User $user, int $perPage = 20): LengthAwarePaginator;

    public function summaryForUser(User $user): array;

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator;
}
