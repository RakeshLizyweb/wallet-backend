<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\Otp;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;

class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $users,
        protected OtpService $otpService,
        protected DeviceService $deviceService,
        protected PinService $pinService,
        protected ReferralService $referralService,
    ) {
    }

    public function register(string $name, string $phone, string $nationality, ?string $ip = null, ?string $referralCode = null): array
    {
        $user = $this->users->findByPhone($phone);

        if ($user && $user->phone_verified_at) {
            throw new ApiException('This phone number is already registered. Please login instead.', 422);
        }

        if (! $user) {
            $user = $this->users->create([
                'name' => $name,
                'phone' => $phone,
                'nationality' => $nationality,
                'upi_handle' => $this->users->generateUniqueUpiHandle($phone),
                'referral_code' => $this->users->generateUniqueReferralCode(),
                'status' => UserStatus::Active->value,
            ]);

            $this->referralService->redeem($user, $referralCode);
        } else {
            $user->update(['name' => $name, 'nationality' => $nationality]);
        }

        $otp = $this->otpService->generate($phone, OtpPurpose::Registration, $ip);

        return ['user' => $user, 'otp' => $otp];
    }

    public function requestLoginOtp(string $phone, ?string $ip = null): Otp
    {
        $user = $this->users->findByPhone($phone);

        if (! $user || ! $user->phone_verified_at) {
            throw new ApiException('No verified account found for this phone number. Please register first.', 404);
        }

        $this->assertAccountUsable($user);

        return $this->otpService->generate($phone, OtpPurpose::Login, $ip);
    }

    public function resendOtp(string $phone, OtpPurpose $purpose, ?string $ip = null): Otp
    {
        return $this->otpService->generate($phone, $purpose, $ip);
    }

    public function verifyAndAuthenticate(string $phone, string $code, OtpPurpose $purpose, ?array $device = null): array
    {
        $this->otpService->verify($phone, $purpose, $code);

        $user = $this->users->findByPhone($phone);

        if (! $user) {
            throw new ApiException('User not found.', 404);
        }

        if ($purpose === OtpPurpose::Registration && ! $user->phone_verified_at) {
            $user->update(['phone_verified_at' => now()]);
        }

        $this->assertAccountUsable($user);

        $token = $user->createToken('wallet-app', ['*'], now()->addDays(30));

        if ($device) {
            $this->deviceService->registerDevice($user, $device, $token->accessToken->id);
        }

        return [
            'user' => $user->fresh(),
            'token' => $token->plainTextToken,
            'requires_pin_setup' => ! $user->hasPin(),
        ];
    }

    public function forgotPin(string $phone, ?string $ip = null): Otp
    {
        $user = $this->users->findByPhone($phone);

        if (! $user || ! $user->phone_verified_at) {
            throw new ApiException('No verified account found for this phone number.', 404);
        }

        $this->assertAccountUsable($user);

        return $this->otpService->generate($phone, OtpPurpose::ResetPin, $ip);
    }

    public function resetPin(string $phone, string $code, string $newPin): User
    {
        $this->otpService->verify($phone, OtpPurpose::ResetPin, $code);

        $user = $this->users->findByPhone($phone);

        if (! $user) {
            throw new ApiException('User not found.', 404);
        }

        return $this->pinService->forceSetPin($user, $newPin);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function logoutAllDevices(User $user): void
    {
        $user->tokens()->delete();
    }

    public function refresh(User $user): string
    {
        $currentToken = $user->currentAccessToken();

        $newToken = $user->createToken('wallet-app', ['*'], now()->addDays(30));

        if ($currentToken) {
            $user->tokens()->where('id', $currentToken->id)->delete();
        }

        return $newToken->plainTextToken;
    }

    public function deactivate(User $user, string $pin): User
    {
        $this->pinService->verifyPin($user, $pin);

        return DB::transaction(function () use ($user) {
            $user->update([
                'status' => UserStatus::Deactivated->value,
                'deactivated_at' => now(),
            ]);

            $user->tokens()->delete();

            return $user->fresh();
        });
    }

    public function deleteAccount(User $user, string $pin): void
    {
        $this->pinService->verifyPin($user, $pin);

        DB::transaction(function () use ($user) {
            $timestamp = now()->timestamp;

            $user->update([
                'status' => UserStatus::Deleted->value,
                'phone' => "deleted_{$timestamp}_{$user->phone}",
                'email' => $user->email ? "deleted_{$timestamp}_{$user->email}" : null,
                'upi_handle' => "deleted_{$timestamp}_{$user->upi_handle}",
            ]);

            $user->tokens()->delete();
            $user->delete();
        });
    }

    protected function assertAccountUsable(User $user): void
    {
        if ($user->status === UserStatus::Deactivated) {
            throw new ApiException('This account has been deactivated.', 403);
        }

        if ($user->status === UserStatus::Deleted) {
            throw new ApiException('This account no longer exists.', 403);
        }
    }
}
