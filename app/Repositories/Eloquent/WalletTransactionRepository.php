<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Repositories\Contracts\WalletTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class WalletTransactionRepository extends BaseRepository implements WalletTransactionRepositoryInterface
{
    public function __construct(WalletTransaction $model)
    {
        parent::__construct($model);
    }

    public function miniStatement(Wallet $wallet, int $limit = 10): Collection
    {
        return $this->model->newQuery()
            ->where('wallet_id', $wallet->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function fullStatement(Wallet $wallet, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->where('wallet_id', $wallet->id);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->latest('id')->paginate($perPage);
    }

    public function sumForUserSince(User $user, array $categories, Carbon $since, string $type = 'debit'): float
    {
        return (float) $this->model->newQuery()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->whereIn('category', $categories)
            ->where('created_at', '>=', $since)
            ->sum('amount');
    }
}
