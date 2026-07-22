<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PinTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_set_pin_once(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/pin', ['pin' => '123456', 'pin_confirmation' => '123456'])
            ->assertOk();

        $this->postJson('/api/v1/pin', ['pin' => '654321', 'pin_confirmation' => '654321'])
            ->assertStatus(422);
    }

    public function test_user_can_change_pin_with_correct_current_pin(): void
    {
        $user = User::factory()->withPin('123456')->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/pin', [
            'current_pin' => '123456',
            'pin' => '654321',
            'pin_confirmation' => '654321',
        ])->assertOk();
    }

    public function test_pin_locks_after_max_failed_attempts(): void
    {
        $user = User::factory()->withPin('123456')->create();
        Sanctum::actingAs($user);

        for ($i = 0; $i < config('wallet.pin.max_attempts'); $i++) {
            $this->putJson('/api/v1/pin', [
                'current_pin' => '000000',
                'pin' => '654321',
                'pin_confirmation' => '654321',
            ])->assertStatus(422);
        }

        $response = $this->putJson('/api/v1/pin', [
            'current_pin' => '123456',
            'pin' => '654321',
            'pin_confirmation' => '654321',
        ]);

        $response->assertStatus(423);
    }
}
