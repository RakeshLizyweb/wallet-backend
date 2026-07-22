<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $projectId = config('services.fcm.project_id');
        $accessToken = config('services.fcm.access_token');

        $tokens = $user->devices()->whereNotNull('fcm_token')->pluck('fcm_token');

        if ($tokens->isEmpty()) {
            return;
        }

        if (! $projectId || ! $accessToken) {
            Log::info("FCM not configured. Would have sent to {$user->id}: {$title} - {$body}");

            return;
        }

        foreach ($tokens as $token) {
            try {
                Http::withToken($accessToken)
                    ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                        'message' => [
                            'token' => $token,
                            'notification' => ['title' => $title, 'body' => $body],
                            'data' => array_map('strval', $data),
                        ],
                    ]);
            } catch (\Throwable $e) {
                Log::warning("FCM push failed for user {$user->id}: {$e->getMessage()}");
            }
        }
    }
}
