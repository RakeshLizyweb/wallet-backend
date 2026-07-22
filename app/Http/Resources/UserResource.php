<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'phone_verified' => ! is_null($this->phone_verified_at),
            'email' => $this->email,
            'upi_handle' => $this->upi_handle,
            'status' => $this->status?->value,
            'tier' => $this->tier?->value,
            'has_pin' => $this->hasPin(),
            'username' => $this->username,
            'has_admin_credentials' => $this->hasAdminCredentials(),
            'roles' => $this->getRoleNames(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
