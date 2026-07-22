<?php

namespace App\Models;

use App\Enums\VirtualCardStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class VirtualCard extends Model
{
    protected $fillable = [
        'user_id',
        'identity_verification_id',
        'card_number_encrypted',
        'card_number_last4',
        'expiry_date',
        'status',
        'activated_at',
    ];

    protected $hidden = [
        'card_number_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'status' => VirtualCardStatus::class,
            'activated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getMaskedCardNumberAttribute(): string
    {
        return '4XXX XXXX XXXX '.$this->card_number_last4;
    }

    public function getDecryptedCardNumberAttribute(): string
    {
        return Crypt::decryptString($this->card_number_encrypted);
    }
}
