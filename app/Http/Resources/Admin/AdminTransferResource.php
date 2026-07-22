<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference_number' => $this->reference_number,
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'amount' => (float) $this->amount,
            'fee' => (float) $this->fee,
            'total_amount' => (float) $this->total_amount,
            'sender' => $this->senderUser ? [
                'id' => $this->senderUser->id,
                'name' => $this->senderUser->name,
                'upi_handle' => $this->senderUser->upi_handle,
            ] : null,
            'receiver' => $this->receiverUser ? [
                'id' => $this->receiverUser->id,
                'name' => $this->receiverUser->name,
                'upi_handle' => $this->receiverUser->upi_handle,
            ] : null,
            'failure_reason' => $this->failure_reason,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
