<?php

namespace App\Repositories\Eloquent;

use App\Models\Referral;
use App\Models\User;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use Illuminate\Support\Collection;

class ReferralRepository extends BaseRepository implements ReferralRepositoryInterface
{
    public function __construct(Referral $model)
    {
        parent::__construct($model);
    }

    public function phoneHasRedeemed(string $phone): bool
    {
        return $this->model->newQuery()->where('referred_phone', $phone)->exists();
    }

    public function findUnrewardedForReferredUser(int $userId): ?Referral
    {
        return $this->model->newQuery()
            ->where('referred_user_id', $userId)
            ->whereNull('rewarded_at')
            ->first();
    }

    public function forReferrer(User $user): Collection
    {
        return $this->model->newQuery()
            ->where('referrer_id', $user->id)
            ->with('referredUser:id,name,phone')
            ->latest('id')
            ->get();
    }
}
