<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Notifications\WalletNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function __construct(protected FcmService $fcmService)
    {
    }

    public function send(User $user, string $title, string $body, string $type, array $meta = []): void
    {
        $user->notify(new WalletNotification($title, $body, $type, $meta));

        $this->fcmService->sendToUser($user, $title, $body, ['type' => $type]);
    }

    public function listForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->notifications()->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAsRead(User $user, string $notificationId): DatabaseNotification
    {
        $notification = $user->notifications()->where('id', $notificationId)->first();

        if (! $notification) {
            throw new ApiException('Notification not found.', 404);
        }

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return $notification;
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }

    public function broadcast(string $title, string $body, string $type, array $meta = []): int
    {
        $count = 0;

        User::whereNotNull('phone_verified_at')->chunkById(200, function ($users) use ($title, $body, $type, $meta, &$count) {
            Notification::send($users, new WalletNotification($title, $body, $type, $meta));
            $count += $users->count();
        });

        return $count;
    }
}
