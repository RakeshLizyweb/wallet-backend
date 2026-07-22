<?php

namespace App\Repositories\Eloquent;

use App\Models\ScratchCard;
use App\Models\User;
use App\Repositories\Contracts\ScratchCardRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ScratchCardRepository extends BaseRepository implements ScratchCardRepositoryInterface
{
    public function __construct(ScratchCard $model)
    {
        parent::__construct($model);
    }

    public function paginateForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate($perPage);
    }

    public function summaryForUser(User $user): array
    {
        $base = $this->model->newQuery()->where('user_id', $user->id);

        return [
            'total_cashback_earned' => (clone $base)
                ->whereIn('reward_type', ['cashback', 'lucky'])
                ->where('is_redeemed', true)
                ->sum('reward_value'),
            'unscratched_count' => (clone $base)->where('is_scratched', false)->count(),
            'unredeemed_count' => (clone $base)->where('is_scratched', true)->where('is_redeemed', false)->count(),
            'reward_points' => $user->reward_points,
        ];
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with('user:id,name,phone,upi_handle');

        if (! empty($filters['reward_type'])) {
            $query->where('reward_type', $filters['reward_type']);
        }

        if (array_key_exists('is_redeemed', $filters) && $filters['is_redeemed'] !== null) {
            $query->where('is_redeemed', $filters['is_redeemed']);
        }

        return $query->latest('id')->paginate($perPage);
    }
}
