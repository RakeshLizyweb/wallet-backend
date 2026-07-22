<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminScratchCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'upi_handle' => $this->user->upi_handle,
            ],
            'reward_type' => $this->reward_type?->value,
            'reward_value' => $this->reward_value !== null ? (float) $this->reward_value : null,
            'coupon_code' => $this->coupon_code,
            'is_scratched' => $this->is_scratched,
            'is_redeemed' => $this->is_redeemed,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
