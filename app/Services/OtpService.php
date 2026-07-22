<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Exceptions\ApiException;
use App\Models\Otp;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function __construct(protected OtpRepositoryInterface $otps)
    {
    }

    public function generate(string $phone, OtpPurpose $purpose, ?string $ip = null): Otp
    {
        $this->guardResendCooldown($phone, $purpose);

        $this->otps->invalidatePrevious($phone, $purpose);

        $code = (string) random_int(
            (int) str_pad('1', config('wallet.otp.length'), '0'),
            (int) str_pad('9', config('wallet.otp.length'), '9')
        );

        $otp = $this->otps->create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose->value,
            'expires_at' => now()->addMinutes(config('wallet.otp.expiry_minutes')),
            'ip_address' => $ip,
        ]);

        Log::info("OTP generated for {$phone} [{$purpose->value}]: {$code}");

        if (app()->environment(['local', 'testing'])) {
            $otp->setAttribute('plain_code', $code);
        }

        return $otp;
    }

    public function verify(string $phone, OtpPurpose $purpose, string $code): Otp
    {
        $otp = $this->otps->latestFor($phone, $purpose);

        if (! $otp) {
            throw new ApiException('No OTP was requested for this phone number.', 422);
        }

        if ($otp->isConsumed()) {
            throw new ApiException('This OTP has already been used.', 422);
        }

        if ($otp->isExpired()) {
            throw new ApiException('This OTP has expired. Please request a new one.', 422);
        }

        if ($otp->attempts >= config('wallet.otp.max_attempts')) {
            throw new ApiException('Maximum OTP attempts exceeded. Please request a new one.', 429);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            throw new ApiException('Invalid OTP code.', 422);
        }

        $otp->update(['consumed_at' => now()]);

        return $otp;
    }

    protected function guardResendCooldown(string $phone, OtpPurpose $purpose): void
    {
        $latest = $this->otps->latestFor($phone, $purpose);

        if (! $latest) {
            return;
        }

        $cooldownEndsAt = $latest->created_at->addSeconds(config('wallet.otp.resend_cooldown_seconds'));

        if (now()->lessThan($cooldownEndsAt)) {
            $secondsLeft = now()->diffInSeconds($cooldownEndsAt);

            throw new ApiException("Please wait {$secondsLeft} seconds before requesting another OTP.", 429);
        }
    }
}
