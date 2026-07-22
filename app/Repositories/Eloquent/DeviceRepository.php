<?php

namespace App\Repositories\Eloquent;

use App\Models\Device;
use App\Models\User;
use App\Repositories\Contracts\DeviceRepositoryInterface;

class DeviceRepository extends BaseRepository implements DeviceRepositoryInterface
{
    public function __construct(Device $model)
    {
        parent::__construct($model);
    }

    public function findForUser(User $user, string $deviceId): ?Device
    {
        return $this->model->newQuery()
            ->where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->first();
    }

    public function updateOrCreateForUser(User $user, string $deviceId, array $attributes): Device
    {
        return $this->model->newQuery()->updateOrCreate(
            ['user_id' => $user->id, 'device_id' => $deviceId],
            $attributes
        );
    }
}
