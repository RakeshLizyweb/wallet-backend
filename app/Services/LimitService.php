<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\Contracts\AccountTransactionRepositoryInterface;
use App\Repositories\Contracts\WalletTransactionRepositoryInterface;
use Illuminate\Support\Carbon;

class LimitService
{
    protected const WALLET_OUT_CATEGORIES = ['wallet_to_wallet', 'wallet_to_bank'];

    // account_to_wallet is excluded: it's a self-transfer between the user's
    // own two balances, the money never leaves their custody, so it doesn't
    // count against the outbound spending limit.
    protected const ACCOUNT_OUT_CATEGORIES = ['account_to_account'];

    public function __construct(
        protected WalletTransactionRepositoryInterface $walletLedger,
        protected AccountTransactionRepositoryInterface $accountLedger,
    ) {
    }

    public function limitsFor(User $user): array
    {
        return config("wallet.limits.{$user->tier->value}", config('wallet.limits.basic'));
    }

    public function assertWithinLimits(User $user, float $amount): void
    {
        $limits = $this->limitsFor($user);

        $this->assertPeriod($user, $amount, $limits['monthly'] ?? null, Carbon::now()->startOfMonth(), 'monthly');
    }

    public function usageFor(User $user): array
    {
        return [
            'monthly' => $this->usedSince($user, Carbon::now()->startOfMonth()),
            'limits' => $this->limitsFor($user),
        ];
    }

    protected function usedSince(User $user, Carbon $since): float
    {
        return $this->walletLedger->sumForUserSince($user, self::WALLET_OUT_CATEGORIES, $since)
            + $this->accountLedger->sumForUserSince($user, self::ACCOUNT_OUT_CATEGORIES, $since);
    }

    protected function assertPeriod(User $user, float $amount, ?float $limit, Carbon $since, string $label): void
    {
        if ($limit === null) {
            return;
        }

        $used = $this->usedSince($user, $since);

        if ($used + $amount > $limit) {
            throw new ApiException(
                "This transaction exceeds your {$label} transaction limit of ".number_format($limit, 2).'.',
                422
            );
        }
    }
}
