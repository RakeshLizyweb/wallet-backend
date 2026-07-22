<?php

namespace App\Repositories\Contracts;

use App\Models\Device;
use App\Models\User;

interface DeviceRepositoryInterface extends BaseRepositoryInterface
{
    public function findForUser(User $user, string $deviceId): ?Device;

    public function updateOrCreateForUser(User $user, string $deviceId, array $attributes): Device;
}
