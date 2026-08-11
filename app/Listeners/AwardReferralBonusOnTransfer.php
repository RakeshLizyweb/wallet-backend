<?php

namespace App\Listeners;

use App\Enums\TransferStatus;
use App\Enums\TransferType;
use App\Events\TransferCompleted;
use App\Models\Transfer;
use App\Services\ReferralService;

class AwardReferralBonusOnTransfer
{
    public function __construct(protected ReferralService $referralService)
    {
    }

    public function handle(TransferCompleted $event): void
    {
        $transfer = $event->transfer;

        // "First transaction" means the first time this user actually moves
        // money to someone/somewhere else — self-transfers between their own
        // Wallet and Account don't count, since that's not really "using"
        // the app the referral program is meant to reward.
        if (! in_array($transfer->type, [TransferType::WalletToWallet, TransferType::AccountToAccount, TransferType::WalletToBank], true)) {
            return;
        }

        $isFirstQualifyingTransfer = Transfer::query()
            ->where('sender_user_id', $transfer->sender_user_id)
            ->where('status', TransferStatus::Success->value)
            ->whereIn('type', [TransferType::WalletToWallet->value, TransferType::AccountToAccount->value, TransferType::WalletToBank->value])
            ->count() === 1;

        if (! $isFirstQualifyingTransfer) {
            return;
        }

        $this->referralService->rewardFirstTransfer($transfer);
    }
}
