<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityVerification extends Model
{
    protected $fillable = [
        'user_id',
        'document_type',
        'document_number',
        'document_expiry',
        'document_image_path',
        'selfie_image_path',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'document_expiry' => 'date',
            'status' => VerificationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
