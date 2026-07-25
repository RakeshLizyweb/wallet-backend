<?php

namespace App\Models;

use App\Enums\TransferStatus;
use App\Enums\TransferType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    protected $fillable = [
        'reference_number',
        'type',
        'sender_user_id',
        'receiver_user_id',
        'sender_wallet_id',
        'receiver_wallet_id',
        'sender_account_id',
        'receiver_account_id',
        'bank_account_id',
        'amount',
        'fee',
        'total_amount',
        'status',
        'sender_note',
        'receiver_note',
        'failure_reason',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransferType::class,
            'status' => TransferStatus::class,
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function receiverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }

    public function senderWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'sender_wallet_id');
    }

    public function receiverWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'receiver_wallet_id');
    }

    public function senderAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sender_account_id');
    }

    public function receiverAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receiver_account_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
