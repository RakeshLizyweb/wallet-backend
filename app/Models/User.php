<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Enums\UserTier;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'phone_verified_at',
        'email',
        'username',
        'password',
        'upi_handle',
        'status',
        'tier',
        'reward_points',
        'pin_hash',
        'pin_set_at',
        'pin_attempts',
        'pin_locked_until',
        'deactivated_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin_hash',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'pin_set_at' => 'datetime',
            'pin_locked_until' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'pin_hash' => 'hashed',
            'status' => UserStatus::class,
            'tier' => UserTier::class,
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function scratchCards(): HasMany
    {
        return $this->hasMany(ScratchCard::class);
    }

    public function hasPin(): bool
    {
        return ! is_null($this->pin_hash);
    }

    public function hasAdminCredentials(): bool
    {
        return ! is_null($this->username) && ! is_null($this->password);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
