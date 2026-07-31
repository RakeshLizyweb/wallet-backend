<?php

namespace App\Repositories\Contracts;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TransferRepositoryInterface extends BaseRepositoryInterface
{
    public function findByReference(string $referenceNumber): ?Transfer;

    public function paginateForUser(User $user, array $filters, int $perPage = 20): LengthAwarePaginator;

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function sumAmountBetween(array $filters): float;

    public function sumFeesBetween(array $filters): float;

    public function recentContacts(User $user, int $limit = 10): Collection;
}
