<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminForgotPasswordRequest;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Requests\Admin\AdminResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AdminAuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminAuthController extends Controller
{
    use ApiResponse;

    public function __construct(protected AdminAuthService $adminAuthService)
    {
    }

    public function login(AdminLoginRequest $request): JsonResponse
    {
        $result = $this->adminAuthService->login($request->username, $request->password);

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Authenticated successfully.');
    }

    public function forgotPassword(AdminForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->adminAuthService->forgotPassword($request->username, $request->ip());

        return $this->success([
            'masked_phone' => $this->maskPhone($result['user']->phone),
            'debug_otp' => $result['otp']->plain_code ?? null,
        ], 'An OTP has been sent to the phone number on file for this admin account.');
    }

    public function resetPassword(AdminResetPasswordRequest $request): JsonResponse
    {
        $this->adminAuthService->resetPassword($request->username, $request->otp, $request->password);

        return $this->success(null, 'Password reset successfully. You can now log in.');
    }

    protected function maskPhone(string $phone): string
    {
        return str_repeat('*', max(strlen($phone) - 4, 0)).substr($phone, -4);
    }
}
