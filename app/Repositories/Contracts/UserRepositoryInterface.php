<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByPhone(string $phone): ?User;

    public function findByUpiHandle(string $upiHandle): ?User;

    public function findByUsername(string $username): ?User;

    public function generateUniqueUpiHandle(string $phone): string;

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator;
}
