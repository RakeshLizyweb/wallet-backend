<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use App\Repositories\Contracts\DeviceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class DeviceService
{
    public function __construct(protected DeviceRepositoryInterface $devices)
    {
    }

    public function registerDevice(User $user, array $data, ?int $tokenId = null): Device
    {
        return $this->devices->updateOrCreateForUser($user, $data['device_id'], [
            'device_name' => $data['device_name'] ?? null,
            'platform' => $data['platform'] ?? null,
            'fcm_token' => $data['fcm_token'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'personal_access_token_id' => $tokenId,
            'last_login_at' => now(),
        ]);
    }

    public function updateFcmToken(User $user, string $deviceId, string $fcmToken): Device
    {
        $device = $this->devices->findForUser($user, $deviceId);

        if (! $device) {
            return $this->devices->updateOrCreateForUser($user, $deviceId, [
                'fcm_token' => $fcmToken,
            ]);
        }

        return $this->devices->update($device, ['fcm_token' => $fcmToken]);
    }

    public function listForUser(User $user): Collection
    {
        return $user->devices()->latest('last_login_at')->get();
    }

    public function removeDevice(User $user, Device $device): bool
    {
        if ($device->personal_access_token_id) {
            $user->tokens()->where('id', $device->personal_access_token_id)->delete();
        }

        return $this->devices->delete($device);
    }
}
