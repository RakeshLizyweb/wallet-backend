<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

interface WalletTransactionRepositoryInterface extends BaseRepositoryInterface
{
    public function miniStatement(Wallet $wallet, int $limit = 10): Collection;

    public function fullStatement(Wallet $wallet, array $filters, int $perPage = 20): LengthAwarePaginator;

    public function sumForUserSince(User $user, array $categories, Carbon $since, string $type = 'debit'): float;
}
