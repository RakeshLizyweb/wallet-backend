<?php

namespace App\Models;

use App\Enums\BankAccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_holder_name',
        'account_number_encrypted',
        'account_number_hash',
        'account_number_last4',
        'ifsc_code',
        'account_type',
        'is_primary',
        'is_verified',
        'verified_at',
    ];

    protected $hidden = [
        'account_number_encrypted',
        'account_number_hash',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => BankAccountType::class,
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDecryptedAccountNumberAttribute(): string
    {
        return Crypt::decryptString($this->account_number_encrypted);
    }

    public function getMaskedAccountNumberAttribute(): string
    {
        return str_repeat('X', 6).$this->account_number_last4;
    }

    public static function hashAccountNumber(string $accountNumber): string
    {
        return hash('sha256', $accountNumber);
    }
}
