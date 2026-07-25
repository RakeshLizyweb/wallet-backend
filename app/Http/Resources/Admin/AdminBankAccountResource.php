<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'phone' => $this->user->phone,
                'upi_handle' => $this->user->upi_handle,
            ],
            'bank_name' => $this->bank_name,
            'account_holder_name' => $this->account_holder_name,
            'masked_account_number' => $this->masked_account_number,
            'ifsc_code' => $this->ifsc_code,
            'is_primary' => $this->is_primary,
            'is_verified' => $this->is_verified,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
