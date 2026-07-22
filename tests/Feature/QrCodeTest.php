<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_their_qr_code(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/qr');

        $response->assertOk()
            ->assertJsonPath('data.upi_handle', $user->upi_handle)
            ->assertJsonStructure(['data' => ['upi_handle', 'wallet_number', 'qr_image']]);
    }

    public function test_validating_a_qr_payload_resolves_the_receiver(): void
    {
        $scanner = User::factory()->create();
        $receiver = User::factory()->create();
        Sanctum::actingAs($scanner);

        $response = $this->postJson('/api/v1/qr/validate', [
            'payload' => json_encode(['v' => 1, 'type' => 'wallet_pay', 'upi' => $receiver->upi_handle]),
        ]);

        $response->assertOk()->assertJsonPath('data.upi_handle', $receiver->upi_handle);
    }

    public function test_invalid_qr_payload_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/qr/validate', ['payload' => 'not-a-valid-payload'])
            ->assertStatus(422);
    }
}
