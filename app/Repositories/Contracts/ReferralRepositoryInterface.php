<?php

namespace App\Repositories\Contracts;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Collection;

interface ReferralRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Whether this phone number has ever redeemed a referral code, under
     * any users row it has ever belonged to (survives account deletion).
     */
    public function phoneHasRedeemed(string $phone): bool;

    public function findUnrewardedForReferredUser(int $userId): ?Referral;

    public function forReferrer(User $user): Collection;
}
