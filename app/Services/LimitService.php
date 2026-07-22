<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\Contracts\WalletTransactionRepositoryInterface;
use Illuminate\Support\Carbon;

class LimitService
{
    protected const TRANSFER_OUT_CATEGORIES = ['wallet_to_wallet', 'wallet_to_bank'];

    public function __construct(protected WalletTransactionRepositoryInterface $ledger)
    {
    }

    public function limitsFor(User $user): array
    {
        return config("wallet.limits.{$user->tier->value}", config('wallet.limits.basic'));
    }

    public function assertWithinLimits(User $user, float $amount): void
    {
        $limits = $this->limitsFor($user);

        $this->assertPeriod($user, $amount, $limits['daily'] ?? null, Carbon::now()->startOfDay(), 'daily');
        $this->assertPeriod($user, $amount, $limits['monthly'] ?? null, Carbon::now()->startOfMonth(), 'monthly');
        $this->assertPeriod($user, $amount, $limits['yearly'] ?? null, Carbon::now()->startOfYear(), 'yearly');
    }

    public function usageFor(User $user): array
    {
        return [
            'daily' => $this->ledger->sumForUserSince($user, self::TRANSFER_OUT_CATEGORIES, Carbon::now()->startOfDay()),
            'monthly' => $this->ledger->sumForUserSince($user, self::TRANSFER_OUT_CATEGORIES, Carbon::now()->startOfMonth()),
            'yearly' => $this->ledger->sumForUserSince($user, self::TRANSFER_OUT_CATEGORIES, Carbon::now()->startOfYear()),
            'limits' => $this->limitsFor($user),
        ];
    }

    protected function assertPeriod(User $user, float $amount, ?float $limit, Carbon $since, string $label): void
    {
        if ($limit === null) {
            return;
        }

        $used = $this->ledger->sumForUserSince($user, self::TRANSFER_OUT_CATEGORIES, $since);

        if ($used + $amount > $limit) {
            throw new ApiException(
                "This transaction exceeds your {$label} transaction limit of ".number_format($limit, 2).'.',
                422
            );
        }
    }
}
