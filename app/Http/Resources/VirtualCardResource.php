<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VirtualCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'masked_card_number' => $this->masked_card_number,
            'expiry_date' => $this->expiry_date?->format('m/y'),
            'status' => $this->status?->value,
            'activated_at' => $this->activated_at?->toIso8601String(),
        ];
    }
}
