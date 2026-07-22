<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateFcmTokenRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\DeviceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    use ApiResponse;

    public function __construct(protected DeviceService $deviceService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success(DeviceResource::collection($this->deviceService->listForUser($request->user())));
    }

    public function updateFcmToken(UpdateFcmTokenRequest $request): JsonResponse
    {
        $device = $this->deviceService->updateFcmToken($request->user(), $request->device_id, $request->fcm_token);

        return $this->success(new DeviceResource($device), 'FCM token updated successfully.');
    }

    public function destroy(Request $request, Device $device): JsonResponse
    {
        $this->authorize('delete', $device);

        $this->deviceService->removeDevice($request->user(), $device);

        return $this->success(null, 'Device removed successfully.');
    }
}
