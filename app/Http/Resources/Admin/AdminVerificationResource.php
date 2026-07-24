<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'phone' => $this->user->phone,
                'nationality' => $this->user->nationality,
            ],
            'document_type' => $this->document_type?->value,
            'document_number' => $this->document_number,
            'document_expiry' => $this->document_expiry?->toDateString(),
            'status' => $this->status?->value,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
