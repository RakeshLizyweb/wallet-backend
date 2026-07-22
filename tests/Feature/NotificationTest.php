<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_and_read_notifications(): void
    {
        $user = User::factory()->create();
        app(NotificationService::class)->send($user, 'Hello', 'World', 'test');

        Sanctum::actingAs($user);

        $list = $this->getJson('/api/v1/notifications');
        $list->assertOk();
        $id = $list->json('data.0.id');

        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 1);

        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk();

        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_mark_all_as_read(): void
    {
        $user = User::factory()->create();
        $service = app(NotificationService::class);
        $service->send($user, 'A', 'A', 'test');
        $service->send($user, 'B', 'B', 'test');

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
    }
}
