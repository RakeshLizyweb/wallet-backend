<?php

namespace App\Services;

use App\Enums\LedgerCategory;
use App\Enums\TransferStatus;
use App\Enums\TransferType;
use App\Events\TransferCompleted;
use App\Exceptions\ApiException;
use App\Models\BankAccount;
use App\Models\Transfer;
use App\Models\User;
use App\Repositories\Contracts\TransferRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function __construct(
        protected TransferRepositoryInterface $transfers,
        protected UserRepositoryInterface $users,
        protected WalletRepositoryInterface $wallets,
        protected WalletService $walletService,
        protected AccountService $accountService,
        protected PinService $pinService,
        protected LimitService $limitService,
    ) {
    }

    public function resolveReceiver(string $identifier): User
    {
        $user = $this->users->findByUpiHandle($identifier)
            ?? $this->users->findByPhone($identifier);

        if (! $user) {
            $wallet = $this->wallets->findByWalletNumber($identifier);
            $user = $wallet?->user;
        }

        if (! $user) {
            throw new ApiException('Recipient not found.', 404);
        }

        return $user;
    }

    /**
     * The sender pays from their Wallet, but the receiving side always lands
     * in the receiver's Account, never their Wallet — Account is the single
     * inbox every incoming payment (or admin credit) lands in; Wallet is a
     * spending pocket each user tops up for themselves via accountToWallet().
     */
    public function walletToWallet(User $sender, string $receiverIdentifier, float $amount, string $pin, ?string $senderNote = null): Transfer
    {
        $this->pinService->verifyPin($sender, $pin);

        $receiver = $this->resolveReceiver($receiverIdentifier);

        if ($receiver->id === $sender->id) {
            throw new ApiException('You cannot send money to yourself.', 422);
        }

        $this->limitService->assertWithinLimits($sender, $amount);

        $senderWallet = $this->walletService->getForUser($sender);
        $receiverAccount = $this->accountService->getForUser($receiver);

        // Sender pays exactly what they typed; the 1% platform fee is
        // deducted from what the recipient receives, not added on top.
        $fee = round($amount * config('wallet.fees.wallet_to_wallet', 0), 2);
        $receiveAmount = round($amount - $fee, 2);
        $reference = $this->walletService->generateReferenceNumber();

        return DB::transaction(function () use ($sender, $receiver, $senderWallet, $receiverAccount, $amount, $fee, $receiveAmount, $reference, $senderNote) {
            $transfer = $this->transfers->create([
                'reference_number' => $reference,
                'type' => TransferType::WalletToWallet->value,
                'sender_user_id' => $sender->id,
                'receiver_user_id' => $receiver->id,
                'sender_wallet_id' => $senderWallet->id,
                'receiver_account_id' => $receiverAccount->id,
                'amount' => $amount,
                'fee' => $fee,
                'total_amount' => $amount,
                'status' => TransferStatus::Pending->value,
                'sender_note' => $senderNote,
            ]);

            $this->walletService->debit(
                $senderWallet, $amount, LedgerCategory::WalletToWallet, $reference, $transfer,
                "Sent to {$receiver->upi_handle}"
            );

            $this->accountService->credit(
                $receiverAccount, $receiveAmount, LedgerCategory::WalletToWallet, $reference, $transfer,
                "Received from {$sender->upi_handle}"
            );

            $transfer->update(['status' => TransferStatus::Success->value, 'completed_at' => now()]);

            TransferCompleted::dispatch($transfer->fresh());

            return $transfer->fresh();
        });
    }

    public function accountToAccount(User $sender, string $receiverIdentifier, float $amount, string $pin, ?string $senderNote = null): Transfer
    {
        $this->pinService->verifyPin($sender, $pin);

        $receiver = $this->resolveReceiver($receiverIdentifier);

        if ($receiver->id === $sender->id) {
            throw new ApiException('You cannot send money to yourself.', 422);
        }

        $this->limitService->assertWithinLimits($sender, $amount);

        $senderAccount = $this->accountService->getForUser($sender);
        $receiverAccount = $this->accountService->getForUser($receiver);

        // Sender pays exactly what they typed; the 1% platform fee is
        // deducted from what the recipient receives, not added on top.
        $fee = round($amount * config('wallet.fees.account_to_account', 0), 2);
        $receiveAmount = round($amount - $fee, 2);
        $reference = $this->walletService->generateReferenceNumber();

        return DB::transaction(function () use ($sender, $receiver, $senderAccount, $receiverAccount, $amount, $fee, $receiveAmount, $reference, $senderNote) {
            $transfer = $this->transfers->create([
                'reference_number' => $reference,
                'type' => TransferType::AccountToAccount->value,
                'sender_user_id' => $sender->id,
                'receiver_user_id' => $receiver->id,
                'sender_account_id' => $senderAccount->id,
                'receiver_account_id' => $receiverAccount->id,
                'amount' => $amount,
                'fee' => $fee,
                'total_amount' => $amount,
                'status' => TransferStatus::Pending->value,
                'sender_note' => $senderNote,
            ]);

            $this->accountService->debit(
                $senderAccount, $amount, LedgerCategory::AccountToAccount, $reference, $transfer,
                "Sent to {$receiver->upi_handle}"
            );

            $this->accountService->credit(
                $receiverAccount, $receiveAmount, LedgerCategory::AccountToAccount, $reference, $transfer,
                "Received from {$sender->upi_handle}"
            );

            $transfer->update(['status' => TransferStatus::Success->value, 'completed_at' => now()]);

            TransferCompleted::dispatch($transfer->fresh());

            return $transfer->fresh();
        });
    }

    /**
     * Moves money from the user's own Account into their own Wallet. Always a
     * self-transfer (sender and receiver are the same user), so it isn't
     * subject to the monthly outbound transfer limit — the
     * money never leaves the user's custody, just relocates between their
     * two in-app balances.
     */
    public function accountToWallet(User $user, float $amount, string $pin, ?string $note = null): Transfer
    {
        $this->pinService->verifyPin($user, $pin);

        $account = $this->accountService->getForUser($user);
        $wallet = $this->walletService->getForUser($user);

        $fee = round($amount * config('wallet.fees.account_to_wallet', 0), 2);
        $total = round($amount + $fee, 2);
        $reference = $this->walletService->generateReferenceNumber();

        return DB::transaction(function () use ($user, $account, $wallet, $amount, $fee, $total, $reference, $note) {
            $transfer = $this->transfers->create([
                'reference_number' => $reference,
                'type' => TransferType::AccountToWallet->value,
                'sender_user_id' => $user->id,
                'receiver_user_id' => $user->id,
                'sender_account_id' => $account->id,
                'receiver_wallet_id' => $wallet->id,
                'amount' => $amount,
                'fee' => $fee,
                'total_amount' => $total,
                'status' => TransferStatus::Pending->value,
                'sender_note' => $note,
            ]);

            $this->accountService->debit(
                $account, $total, LedgerCategory::AccountToWallet, $reference, $transfer,
                'Moved to wallet'
            );

            $this->walletService->credit(
                $wallet, $amount, LedgerCategory::AccountToWallet, $reference, $transfer,
                'Moved from account'
            );

            $transfer->update(['status' => TransferStatus::Success->value, 'completed_at' => now()]);

            TransferCompleted::dispatch($transfer->fresh());

            return $transfer->fresh();
        });
    }

    /**
     * Moves money from the user's own Wallet into their own Account — the
     * reverse of accountToWallet(). Same self-transfer exemption applies: not
     * subject to the monthly outbound transfer limit and doesn't touch any
     * bank account, straight between the user's own two in-app balances.
     */
    public function walletToAccount(User $user, float $amount, string $pin, ?string $note = null): Transfer
    {
        $this->pinService->verifyPin($user, $pin);

        $wallet = $this->walletService->getForUser($user);
        $account = $this->accountService->getForUser($user);

        $fee = round($amount * config('wallet.fees.wallet_to_account', 0), 2);
        $total = round($amount + $fee, 2);
        $reference = $this->walletService->generateReferenceNumber();

        return DB::transaction(function () use ($user, $wallet, $account, $amount, $fee, $total, $reference, $note) {
            $transfer = $this->transfers->create([
                'reference_number' => $reference,
                'type' => TransferType::WalletToAccount->value,
                'sender_user_id' => $user->id,
                'receiver_user_id' => $user->id,
                'sender_wallet_id' => $wallet->id,
                'receiver_account_id' => $account->id,
                'amount' => $amount,
                'fee' => $fee,
                'total_amount' => $total,
                'status' => TransferStatus::Pending->value,
                'sender_note' => $note,
            ]);

            $this->walletService->debit(
                $wallet, $total, LedgerCategory::WalletToAccount, $reference, $transfer,
                'Moved to account'
            );

            $this->accountService->credit(
                $account, $amount, LedgerCategory::WalletToAccount, $reference, $transfer,
                'Moved from wallet'
            );

            $transfer->update(['status' => TransferStatus::Success->value, 'completed_at' => now()]);

            TransferCompleted::dispatch($transfer->fresh());

            return $transfer->fresh();
        });
    }

    public function walletToBank(User $user, BankAccount $bankAccount, float $amount, string $pin, ?string $note = null): Transfer
    {
        $this->pinService->verifyPin($user, $pin);

        if ($bankAccount->user_id !== $user->id) {
            throw new ApiException('This bank account does not belong to your account.', 403);
        }

        if (! $bankAccount->is_verified) {
            throw new ApiException('This bank account must be verified before you can withdraw to it.', 422);
        }

        $this->limitService->assertWithinLimits($user, $amount);

        $wallet = $this->walletService->getForUser($user);

        $fee = round($amount * config('wallet.fees.wallet_to_bank', 0), 2);
        $total = round($amount + $fee, 2);
        $reference = $this->walletService->generateReferenceNumber();

        return DB::transaction(function () use ($user, $wallet, $bankAccount, $amount, $fee, $total, $reference, $note) {
            $transfer = $this->transfers->create([
                'reference_number' => $reference,
                'type' => TransferType::WalletToBank->value,
                'sender_user_id' => $user->id,
                'sender_wallet_id' => $wallet->id,
                'bank_account_id' => $bankAccount->id,
                'amount' => $amount,
                'fee' => $fee,
                'total_amount' => $total,
                'status' => TransferStatus::Pending->value,
                'sender_note' => $note,
            ]);

            $this->walletService->debit(
                $wallet, $total, LedgerCategory::WalletToBank, $reference, $transfer,
                "Withdrawal to {$bankAccount->bank_name} ({$bankAccount->masked_account_number})"
            );

            $transfer->update(['status' => TransferStatus::Success->value, 'completed_at' => now()]);

            TransferCompleted::dispatch($transfer->fresh());

            return $transfer->fresh();
        });
    }

    public function bankToWallet(User $user, BankAccount $bankAccount, float $amount, ?string $note = null): Transfer
    {
        if ($bankAccount->user_id !== $user->id) {
            throw new ApiException('This bank account does not belong to your account.', 403);
        }

        $wallet = $this->walletService->getForUser($user);

        $fee = round($amount * config('wallet.fees.bank_to_wallet', 0), 2);
        $creditAmount = round($amount - $fee, 2);
        $reference = $this->walletService->generateReferenceNumber();

        return DB::transaction(function () use ($user, $wallet, $bankAccount, $amount, $fee, $creditAmount, $reference, $note) {
            $transfer = $this->transfers->create([
                'reference_number' => $reference,
                'type' => TransferType::BankToWallet->value,
                'sender_user_id' => $user->id,
                'receiver_wallet_id' => $wallet->id,
                'bank_account_id' => $bankAccount->id,
                'amount' => $amount,
                'fee' => $fee,
                'total_amount' => $amount,
                'status' => TransferStatus::Pending->value,
                'sender_note' => $note,
            ]);

            $this->walletService->credit(
                $wallet, $creditAmount, LedgerCategory::BankToWallet, $reference, $transfer,
                "Deposit from {$bankAccount->bank_name} ({$bankAccount->masked_account_number})"
            );

            $transfer->update(['status' => TransferStatus::Success->value, 'completed_at' => now()]);

            TransferCompleted::dispatch($transfer->fresh());

            return $transfer->fresh();
        });
    }

    public function reverse(Transfer $transfer, string $reason, TransferStatus $resultStatus = TransferStatus::Reversed): Transfer
    {
        if ($transfer->status !== TransferStatus::Success) {
            throw new ApiException('Only successful transfers can be reversed.', 422);
        }

        $reference = 'REV'.$transfer->reference_number;

        return DB::transaction(function () use ($transfer, $reason, $resultStatus, $reference) {
            if ($transfer->type === TransferType::WalletToWallet) {
                $this->walletService->credit(
                    $transfer->senderWallet, (float) $transfer->total_amount, LedgerCategory::Reversal,
                    $reference, $transfer, "Reversal: {$reason}"
                );

                // The receiver only ever got amount minus the platform fee.
                $this->accountService->debit(
                    $transfer->receiverAccount, (float) $transfer->amount - (float) $transfer->fee, LedgerCategory::Reversal,
                    $reference, $transfer, "Reversal: {$reason}"
                );
            } elseif ($transfer->type === TransferType::WalletToBank) {
                $this->walletService->credit(
                    $transfer->senderWallet, (float) $transfer->total_amount, LedgerCategory::Reversal,
                    $reference, $transfer, "Reversal: {$reason}"
                );
            } elseif ($transfer->type === TransferType::BankToWallet) {
                $this->walletService->debit(
                    $transfer->receiverWallet, (float) $transfer->amount, LedgerCategory::Reversal,
                    $reference, $transfer, "Reversal: {$reason}"
                );
            } elseif ($transfer->type === TransferType::AccountToAccount) {
                $this->accountService->credit(
                    $transfer->senderAccount, (float) $transfer->total_amount, LedgerCategory::Reversal,
                    $reference, $transfer, "Reversal: {$reason}"
                );

                // The receiver only ever got amount minus the platform fee.
                $this->accountService->debit(
                    $transfer->receiverAccount, (float) $transfer->amount - (float) $transfer->fee, LedgerCategory::Reversal,
                    $reference, $transfer, "Reversal: {$reason}"
                );
            } elseif ($transfer->type === TransferType::AccountToWallet) {
                $this->accountService->credit(
                    $transfer->senderAccount, (float) $transfer->total_amount, LedgerCategory::Reversal,
                    $reference, $transfer, "Reversal: {$reason}"
                );

                $this->walletService->debit(
                    $transfer->receiverWallet, (float) $transfer->amount, LedgerCategory::Reversal,
                    $reference, $transfer, "Reversal: {$reason}"
                );
            } elseif ($transfer->type === TransferType::WalletToAccount) {
                $this->walletService->credit(
                    $transfer->senderWallet, (float) $transfer->total_amount, LedgerCategory::Reversal,
                    $reference, $transfer, "Reversal: {$reason}"
                );

                $this->accountService->debit(
                    $transfer->receiverAccount, (float) $transfer->amount, LedgerCategory::Reversal,
                    $reference, $transfer, "Reversal: {$reason}"
                );
            }

            $transfer->update([
                'status' => $resultStatus->value,
                'failure_reason' => $reason,
            ]);

            return $transfer->fresh();
        });
    }

    public function findByReference(string $referenceNumber): Transfer
    {
        $transfer = $this->transfers->findByReference($referenceNumber);

        if (! $transfer) {
            throw new ApiException('Transaction not found.', 404);
        }

        return $transfer;
    }

    public function historyForUser(User $user, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->transfers->paginateForUser($user, $filters, $perPage);
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->transfers->paginateAll($filters, $perPage);
    }
}
