<?php

namespace App\Models;

use App\Enums\RewardType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScratchCard extends Model
{
    protected $fillable = [
        'user_id',
        'transfer_id',
        'reward_type',
        'reward_value',
        'coupon_code',
        'is_scratched',
        'scratched_at',
        'is_redeemed',
        'redeemed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'reward_type' => RewardType::class,
            'reward_value' => 'decimal:2',
            'is_scratched' => 'boolean',
            'scratched_at' => 'datetime',
            'is_redeemed' => 'boolean',
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
