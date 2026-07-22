<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BroadcastNotificationRequest;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(protected NotificationService $notificationService)
    {
    }

    public function broadcast(BroadcastNotificationRequest $request): JsonResponse
    {
        $count = $this->notificationService->broadcast($request->title, $request->body, 'admin_broadcast');

        return $this->success(['sent_to' => $count], 'Notification broadcast to all users.');
    }
}
