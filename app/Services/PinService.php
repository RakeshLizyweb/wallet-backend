<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PinService
{
    public function setPin(User $user, string $pin): User
    {
        if ($user->hasPin()) {
            throw new ApiException('PIN already set. Use change PIN instead.', 422);
        }

        return $this->forceSetPin($user, $pin);
    }

    public function changePin(User $user, string $currentPin, string $newPin): User
    {
        $this->verifyPin($user, $currentPin);

        return $this->forceSetPin($user, $newPin);
    }

    public function verifyPin(User $user, string $pin): bool
    {
        if (! $user->hasPin()) {
            throw new ApiException('PIN has not been set for this account.', 422);
        }

        if ($user->pin_locked_until && $user->pin_locked_until->isFuture()) {
            $minutesLeft = now()->diffInMinutes($user->pin_locked_until) + 1;

            throw new ApiException("PIN locked due to too many failed attempts. Try again in {$minutesLeft} minute(s).", 423);
        }

        if (! Hash::check($pin, $user->pin_hash)) {
            $this->registerFailedAttempt($user);

            throw new ApiException('Incorrect PIN.', 422);
        }

        if ($user->pin_attempts > 0 || $user->pin_locked_until) {
            $user->update(['pin_attempts' => 0, 'pin_locked_until' => null]);
        }

        return true;
    }

    public function forceSetPin(User $user, string $pin): User
    {
        $user->update([
            'pin_hash' => $pin,
            'pin_set_at' => now(),
            'pin_attempts' => 0,
            'pin_locked_until' => null,
        ]);

        return $user->fresh();
    }

    protected function registerFailedAttempt(User $user): void
    {
        $attempts = $user->pin_attempts + 1;
        $maxAttempts = config('wallet.pin.max_attempts');

        $payload = ['pin_attempts' => $attempts];

        if ($attempts >= $maxAttempts) {
            $payload['pin_locked_until'] = now()->addMinutes(config('wallet.pin.lockout_minutes'));
        }

        $user->update($payload);
    }
}
