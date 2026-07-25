<?php

namespace App\Listeners;

use App\Enums\TransferType;
use App\Events\TransferCompleted;
use App\Services\NotificationService;

class SendTransferNotifications
{
    public function __construct(protected NotificationService $notificationService)
    {
    }

    public function handle(TransferCompleted $event): void
    {
        $transfer = $event->transfer;

        match ($transfer->type) {
            TransferType::WalletToWallet => $this->notifyWalletToWallet($transfer),
            TransferType::WalletToBank => $this->notificationService->send(
                $transfer->senderUser,
                'Withdrawal successful',
                "{$transfer->amount} CFA was withdrawn to your bank account.",
                'wallet_to_bank',
                ['reference_number' => $transfer->reference_number]
            ),
            TransferType::BankToWallet => $this->notificationService->send(
                $transfer->senderUser,
                'Deposit successful',
                "{$transfer->amount} CFA was added to your wallet.",
                'bank_to_wallet',
                ['reference_number' => $transfer->reference_number]
            ),
            TransferType::AccountToAccount => $this->notifyAccountToAccount($transfer),
            TransferType::AccountToWallet => $this->notificationService->send(
                $transfer->senderUser,
                'Moved to wallet',
                "{$transfer->amount} CFA was moved from your account to your wallet.",
                'account_to_wallet',
                ['reference_number' => $transfer->reference_number]
            ),
        };
    }

    protected function notifyWalletToWallet($transfer): void
    {
        $this->notificationService->send(
            $transfer->senderUser,
            'Money sent',
            "{$transfer->amount} CFA sent to {$transfer->receiverUser->name}.",
            'wallet_to_wallet_sent',
            ['reference_number' => $transfer->reference_number]
        );

        $this->notificationService->send(
            $transfer->receiverUser,
            'Money received',
            "{$transfer->amount} CFA received from {$transfer->senderUser->name} into your account.",
            'wallet_to_wallet_received',
            ['reference_number' => $transfer->reference_number]
        );
    }

    protected function notifyAccountToAccount($transfer): void
    {
        $this->notificationService->send(
            $transfer->senderUser,
            'Money sent',
            "{$transfer->amount} CFA sent to {$transfer->receiverUser->name} from your account.",
            'account_to_account_sent',
            ['reference_number' => $transfer->reference_number]
        );

        $this->notificationService->send(
            $transfer->receiverUser,
            'Money received',
            "{$transfer->amount} CFA received from {$transfer->senderUser->name} into your account.",
            'account_to_account_received',
            ['reference_number' => $transfer->reference_number]
        );
    }
}
