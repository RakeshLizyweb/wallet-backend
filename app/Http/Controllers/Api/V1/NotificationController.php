<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(protected NotificationService $notificationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            NotificationResource::collection($this->notificationService->listForUser(
                $request->user(),
                (int) $request->input('per_page', 20)
            ))
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(['unread_count' => $this->notificationService->unreadCount($request->user())]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $result = $this->notificationService->markAsRead($request->user(), $notification);

        return $this->success(new NotificationResource($result), 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllAsRead($request->user());

        return $this->success(null, 'All notifications marked as read.');
    }
}
