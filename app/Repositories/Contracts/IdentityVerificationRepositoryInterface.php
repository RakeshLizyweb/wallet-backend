<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use App\Models\IdentityVerification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface IdentityVerificationRepositoryInterface extends BaseRepositoryInterface
{
    public function latestForUser(User $user): ?IdentityVerification;

    public function historyForUser(User $user): Collection;

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator;
}
