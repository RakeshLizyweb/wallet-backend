<?php

namespace App\Repositories\Contracts;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

interface AccountTransactionRepositoryInterface extends BaseRepositoryInterface
{
    public function miniStatement(Account $account, int $limit = 10): Collection;

    public function fullStatement(Account $account, array $filters, int $perPage = 20): LengthAwarePaginator;

    public function sumForUserSince(User $user, array $categories, Carbon $since, string $type = 'debit'): float;
}
