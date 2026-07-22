<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Enums\UserTier;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminUserService
{
    public function __construct(protected UserRepositoryInterface $users)
    {
    }

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->users->paginateAll($filters, $perPage);
    }

    public function find(int $id): User
    {
        return $this->users->findOrFail($id);
    }

    public function updateStatus(User $user, UserStatus $status): User
    {
        $payload = ['status' => $status->value];

        if ($status === UserStatus::Deactivated) {
            $payload['deactivated_at'] = now();
        }

        return $this->users->update($user, $payload);
    }

    public function updateTier(User $user, UserTier $tier): User
    {
        return $this->users->update($user, ['tier' => $tier->value]);
    }

    public function assignRole(User $user, string $role): User
    {
        if (! in_array($role, ['super-admin', 'admin', 'support'], true)) {
            throw new ApiException('Invalid role.', 422);
        }

        $user->syncRoles([$role]);

        return $user->fresh();
    }

    public function revokeAdminRoles(User $user): User
    {
        $user->syncRoles([]);

        return $user->fresh();
    }
}
