<?php

namespace App\Services;

use App\Enums\LedgerCategory;
use App\Enums\RewardType;
use App\Enums\TransferType;
use App\Exceptions\ApiException;
use App\Models\ScratchCard;
use App\Models\Transfer;
use App\Models\User;
use App\Repositories\Contracts\ScratchCardRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RewardService
{
    protected const REWARDABLE_TYPES = [TransferType::WalletToWallet, TransferType::WalletToBank];

    public function __construct(
        protected ScratchCardRepositoryInterface $scratchCards,
        protected WalletService $walletService,
        protected NotificationService $notificationService,
    ) {
    }

    public function createForTransfer(Transfer $transfer): ?ScratchCard
    {
        if (! in_array($transfer->type, self::REWARDABLE_TYPES, true)) {
            return null;
        }

        if ((float) $transfer->amount < config('wallet.rewards.min_transaction_for_reward', 100)) {
            return null;
        }

        [$rewardType, $rewardValue, $couponCode] = $this->rollReward((float) $transfer->amount);

        $card = $this->scratchCards->create([
            'user_id' => $transfer->sender_user_id,
            'transfer_id' => $transfer->id,
            'reward_type' => $rewardType->value,
            'reward_value' => $rewardValue,
            'coupon_code' => $couponCode,
            'expires_at' => now()->addDays(30),
        ]);

        $this->notificationService->send(
            $transfer->senderUser,
            'You earned a reward!',
            'Scratch your new reward card to reveal what you won.',
            'scratch_card_earned',
            ['scratch_card_id' => $card->id]
        );

        return $card;
    }

    protected function rollReward(float $transactionAmount): array
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 2 => [RewardType::Lucky, round($transactionAmount * config('wallet.rewards.cashback_rate', 0.01) * 5, 2), null],
            $roll <= 10 => [RewardType::Coupon, null, 'WALLET-'.Str::upper(Str::random(8))],
            $roll <= 30 => [RewardType::Points, (float) config('wallet.rewards.points_per_transaction', 10), null],
            default => [RewardType::Cashback, round($transactionAmount * config('wallet.rewards.cashback_rate', 0.01), 2), null],
        };
    }

    public function scratch(User $user, ScratchCard $card): ScratchCard
    {
        $this->assertOwnership($user, $card);

        if ($card->is_scratched) {
            throw new ApiException('This scratch card has already been scratched.', 422);
        }

        if ($card->isExpired()) {
            throw new ApiException('This scratch card has expired.', 422);
        }

        $card->update(['is_scratched' => true, 'scratched_at' => now()]);

        return $card->fresh();
    }

    public function redeem(User $user, ScratchCard $card): ScratchCard
    {
        $this->assertOwnership($user, $card);

        if (! $card->is_scratched) {
            throw new ApiException('Scratch this card before redeeming.', 422);
        }

        if ($card->is_redeemed) {
            throw new ApiException('This reward has already been redeemed.', 422);
        }

        if ($card->isExpired()) {
            throw new ApiException('This scratch card has expired.', 422);
        }

        return DB::transaction(function () use ($user, $card) {
            if (in_array($card->reward_type, [RewardType::Cashback, RewardType::Lucky], true)) {
                $wallet = $this->walletService->getForUser($user);

                $this->walletService->credit(
                    $wallet,
                    (float) $card->reward_value,
                    LedgerCategory::Reward,
                    null,
                    $card,
                    'Scratch card reward: '.$card->reward_type->value
                );
            } elseif ($card->reward_type === RewardType::Points) {
                $user->increment('reward_points', (int) $card->reward_value);
            }

            $card->update(['is_redeemed' => true, 'redeemed_at' => now()]);

            return $card->fresh();
        });
    }

    public function listForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->scratchCards->paginateForUser($user, $perPage);
    }

    public function summaryForUser(User $user): array
    {
        return $this->scratchCards->summaryForUser($user);
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->scratchCards->paginateAll($filters, $perPage);
    }

    protected function assertOwnership(User $user, ScratchCard $card): void
    {
        if ($card->user_id !== $user->id) {
            throw new ApiException('This reward does not belong to your account.', 403);
        }
    }
}
