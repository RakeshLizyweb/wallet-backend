<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScratchCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_scratched' => $this->is_scratched,
            'is_redeemed' => $this->is_redeemed,
            'expired' => $this->isExpired(),
            'reward' => $this->when($this->is_scratched, fn () => [
                'type' => $this->reward_type?->value,
                'value' => $this->reward_value !== null ? (float) $this->reward_value : null,
                'coupon_code' => $this->coupon_code,
            ]),
            'scratched_at' => $this->scratched_at?->toIso8601String(),
            'redeemed_at' => $this->redeemed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
