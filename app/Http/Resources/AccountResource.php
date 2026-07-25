<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_number' => $this->account_number,
            'balance' => (float) $this->balance,
            'frozen_balance' => (float) $this->frozen_balance,
            'available_balance' => (float) $this->available_balance,
            'currency' => $this->currency,
            'status' => $this->status?->value,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'phone' => $this->user->phone,
                'upi_handle' => $this->user->upi_handle,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
