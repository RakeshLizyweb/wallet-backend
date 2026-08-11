<?php

namespace App\Services;

use App\Enums\LedgerCategory;
use App\Models\Referral;
use App\Models\Transfer;
use App\Models\User;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    public function __construct(
        protected ReferralRepositoryInterface $referrals,
        protected UserRepositoryInterface $users,
        protected AccountService $accountService,
        protected NotificationService $notificationService,
    ) {
    }

    /**
     * Every user has a referral code, but existing users predating this
     * feature won't have one from the users table default — generate and
     * persist one lazily the first time it's needed instead of requiring a
     * backfill migration.
     */
    public function getOrCreateCodeForUser(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        $code = $this->users->generateUniqueReferralCode();
        $user->update(['referral_code' => $code]);

        return $code;
    }

    /**
     * Links a newly registered user to whoever's code they entered. This is
     * intentionally non-blocking: an invalid code, a self-referral, or a
     * phone number that has already redeemed one before (including under a
     * previous, since-deleted account) just means no referral link is
     * created — registration itself always proceeds either way.
     */
    public function redeem(User $newUser, ?string $code): void
    {
        if (! $code) {
            return;
        }

        $referrer = $this->users->findByReferralCode(strtoupper(trim($code)));

        if (! $referrer || $referrer->id === $newUser->id) {
            return;
        }

        if ($this->referrals->phoneHasRedeemed($newUser->phone)) {
            return;
        }

        DB::transaction(function () use ($referrer, $newUser, $code) {
            $this->referrals->create([
                'referrer_id' => $referrer->id,
                'referred_user_id' => $newUser->id,
                'referred_phone' => $newUser->phone,
                'code' => strtoupper(trim($code)),
            ]);

            $newUser->update(['referred_by_id' => $referrer->id]);
        });
    }

    /**
     * Pays out the referral bonus the moment the referred person completes
     * their first-ever successful transfer as sender. Called from
     * AwardReferralBonusOnTransfer, which already checked this is that
     * user's first transfer before calling in.
     */
    public function rewardFirstTransfer(Transfer $transfer): void
    {
        $referral = $this->referrals->findUnrewardedForReferredUser($transfer->sender_user_id);

        if (! $referral) {
            return;
        }

        DB::transaction(function () use ($referral) {
            // Lock the row via a fresh select-for-update-like re-check inside
            // the transaction so a race between two qualifying transfers
            // (shouldn't happen given "first transfer" gating, but cheap
            // insurance) can never pay out twice.
            $locked = Referral::whereKey($referral->id)->whereNull('rewarded_at')->lockForUpdate()->first();

            if (! $locked) {
                return;
            }

            $referrerBonus = (float) config('wallet.referrals.referrer_bonus');
            $referredBonus = (float) config('wallet.referrals.referred_bonus');

            $referrerAccount = $locked->referrer->account;
            $referredAccount = $locked->referredUser->account;

            if ($referrerAccount) {
                $this->accountService->credit(
                    $referrerAccount,
                    $referrerBonus,
                    LedgerCategory::ReferralBonus,
                    null,
                    $locked,
                    "Referral bonus for referring {$locked->referredUser->name}"
                );
            }

            if ($referredAccount) {
                $this->accountService->credit(
                    $referredAccount,
                    $referredBonus,
                    LedgerCategory::ReferralBonus,
                    null,
                    $locked,
                    'Welcome bonus for joining via a referral'
                );
            }

            $locked->update(['rewarded_at' => now()]);

            if ($referrerAccount) {
                $this->notificationService->send(
                    $locked->referrer,
                    'Referral bonus earned!',
                    "{$locked->referredUser->name} made their first transaction — you earned a referral bonus.",
                    'referral_bonus_earned'
                );
            }

            if ($referredAccount) {
                $this->notificationService->send(
                    $locked->referredUser,
                    'Welcome bonus credited!',
                    'Thanks for completing your first transaction — your referral welcome bonus has been added to your account.',
                    'referral_bonus_earned'
                );
            }
        });
    }

    public function summaryForUser(User $user): array
    {
        $referrals = $this->referrals->forReferrer($user);

        return [
            'code' => $this->getOrCreateCodeForUser($user),
            'referrer_bonus' => (float) config('wallet.referrals.referrer_bonus'),
            'referred_bonus' => (float) config('wallet.referrals.referred_bonus'),
            'total_referred' => $referrals->count(),
            'total_rewarded' => $referrals->filter(fn (Referral $r) => $r->isRewarded())->count(),
            'total_earned' => $referrals->filter(fn (Referral $r) => $r->isRewarded())->count()
                * (float) config('wallet.referrals.referrer_bonus'),
            'referrals' => $referrals->map(fn (Referral $r) => [
                'name' => $r->referredUser?->name,
                'phone' => $r->referredUser?->phone,
                'rewarded' => $r->isRewarded(),
                'joined_at' => $r->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
