<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPinRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PinConfirmationRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\ResetPinRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(protected AuthService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register(
            $request->name,
            $request->phone,
            $request->nationality,
            $request->ip(),
            $request->input('referral_code')
        );

        return $this->success(
            ['phone' => $result['user']->phone, 'debug_otp' => $result['otp']->plain_code ?? null],
            'OTP sent for registration verification.'
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $otp = $this->authService->requestLoginOtp($request->phone, $request->ip());

        return $this->success(
            ['phone' => $request->phone, 'debug_otp' => $otp->plain_code ?? null],
            'OTP sent for login verification.'
        );
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->authService->verifyAndAuthenticate(
            $request->phone,
            $request->otp,
            OtpPurpose::from($request->purpose),
            $request->input('device')
        );

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'requires_pin_setup' => $result['requires_pin_setup'],
        ], 'Authenticated successfully.');
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $otp = $this->authService->resendOtp(
            $request->phone,
            OtpPurpose::from($request->purpose),
            $request->ip()
        );

        return $this->success(['debug_otp' => $otp->plain_code ?? null], 'OTP resent successfully.');
    }

    public function forgotPin(ForgotPinRequest $request): JsonResponse
    {
        $otp = $this->authService->forgotPin($request->phone, $request->ip());

        return $this->success(['debug_otp' => $otp->plain_code ?? null], 'OTP sent to reset your PIN.');
    }

    public function resetPin(ResetPinRequest $request): JsonResponse
    {
        $this->authService->resetPin($request->phone, $request->otp, $request->pin);

        return $this->success(null, 'PIN reset successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Logged out successfully.');
    }

    public function logoutAllDevices(Request $request): JsonResponse
    {
        $this->authService->logoutAllDevices($request->user());

        return $this->success(null, 'Logged out from all devices.');
    }

    public function refresh(Request $request): JsonResponse
    {
        $token = $this->authService->refresh($request->user());

        return $this->success(['token' => $token], 'Token refreshed successfully.');
    }

    public function deactivate(PinConfirmationRequest $request): JsonResponse
    {
        $this->authService->deactivate($request->user(), $request->pin);

        return $this->success(null, 'Account deactivated successfully.');
    }

    public function deleteAccount(PinConfirmationRequest $request): JsonResponse
    {
        $this->authService->deleteAccount($request->user(), $request->pin);

        return $this->success(null, 'Account deleted successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }
}
