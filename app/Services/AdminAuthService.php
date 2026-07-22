<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AdminAuthService
{
    protected const ADMIN_ROLES = ['super-admin', 'admin', 'support'];

    public function __construct(
        protected UserRepositoryInterface $users,
        protected OtpService $otpService,
    ) {
    }

    public function login(string $username, string $password): array
    {
        $user = $this->users->findByUsername($username);

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw new ApiException('Invalid username or password.', 401);
        }

        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            throw new ApiException('This account does not have admin access.', 403);
        }

        if ($user->status !== UserStatus::Active) {
            throw new ApiException('This account is not active.', 403);
        }

        $token = $user->createToken('admin-web', ['*'], now()->addDays(7));

        return [
            'user' => $user->fresh(),
            'token' => $token->plainTextToken,
        ];
    }

    public function forgotPassword(string $username, ?string $ip = null): array
    {
        $user = $this->resolveAdminByUsername($username);

        $otp = $this->otpService->generate($user->phone, OtpPurpose::AdminPasswordReset, $ip);

        return ['user' => $user, 'otp' => $otp];
    }

    public function resetPassword(string $username, string $code, string $newPassword): User
    {
        $user = $this->resolveAdminByUsername($username);

        $this->otpService->verify($user->phone, OtpPurpose::AdminPasswordReset, $code);

        return $this->users->update($user, ['password' => $newPassword]);
    }

    protected function resolveAdminByUsername(string $username): User
    {
        $user = $this->users->findByUsername($username);

        if (! $user || ! $user->hasAnyRole(self::ADMIN_ROLES)) {
            throw new ApiException('No admin account found for this username.', 404);
        }

        return $user;
    }

    public function setCredentials(User $user, string $username, string $password): User
    {
        $existing = $this->users->findByUsername($username);

        if ($existing && $existing->id !== $user->id) {
            throw new ApiException('This username is already taken.', 422);
        }

        return $this->users->update($user, [
            'username' => $username,
            'password' => $password,
        ]);
    }
}
