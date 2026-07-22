<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;

        // wallet_to_bank and bank_to_wallet are always self-transfers (sender_user_id
        // is the acting user on both), so direction must be derived from the transfer
        // type rather than from sender/receiver comparison, which only distinguishes
        // parties for wallet_to_wallet transfers.
        $direction = match ($this->type?->value) {
            'bank_to_wallet' => 'credit',
            'wallet_to_bank' => 'debit',
            default => $this->sender_user_id === $userId ? 'debit' : 'credit',
        };

        return [
            'reference_number' => $this->reference_number,
            'type' => $this->type?->value,
            'direction' => $direction,
            'status' => $this->status?->value,
            'amount' => (float) $this->amount,
            'fee' => (float) $this->fee,
            'total_amount' => (float) $this->total_amount,
            'counterparty' => $this->when($this->type?->value === 'wallet_to_wallet', function () use ($direction) {
                $counterparty = $direction === 'debit' ? $this->receiverUser : $this->senderUser;

                return $counterparty ? [
                    'name' => $counterparty->name,
                    'upi_handle' => $counterparty->upi_handle,
                ] : null;
            }),
            'bank_account' => $this->when($this->bankAccount, fn () => [
                'bank_name' => $this->bankAccount->bank_name,
                'masked_account_number' => $this->bankAccount->masked_account_number,
            ]),
            'sender_note' => $this->sender_note,
            'receiver_note' => $this->receiver_note,
            'failure_reason' => $this->failure_reason,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
