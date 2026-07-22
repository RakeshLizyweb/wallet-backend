<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_verify_otp(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '9876543210',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $otpCode = $response->json('data.debug_otp');
        $this->assertNotNull($otpCode);

        $verify = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '9876543210',
            'otp' => $otpCode,
            'purpose' => 'registration',
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.requires_pin_setup', true)
            ->assertJsonStructure(['data' => ['user', 'token', 'requires_pin_setup']]);

        $this->assertDatabaseHas('users', ['phone' => '9876543210']);
        $this->assertNotNull(User::where('phone', '9876543210')->first()->phone_verified_at);
    }

    public function test_registration_creates_a_wallet_automatically(): void
    {
        $user = User::factory()->unverified()->create(['phone' => '9111111111']);

        $this->assertDatabaseHas('wallets', ['user_id' => $user->id]);
    }

    public function test_invalid_otp_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'Test User', 'phone' => '9876543211']);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '9876543211',
            'otp' => '000000',
            'purpose' => 'registration',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_login_requires_an_already_verified_account(): void
    {
        $response = $this->postJson('/api/v1/auth/login', ['phone' => '9000000000']);

        $response->assertStatus(404);
    }

    public function test_login_sends_otp_for_existing_verified_user(): void
    {
        User::factory()->create(['phone' => '9123456780']);

        $response = $this->postJson('/api/v1/auth/login', ['phone' => '9123456780']);

        $response->assertOk();
        $this->assertDatabaseHas('otps', ['phone' => '9123456780', 'purpose' => 'login']);
    }

    public function test_forgot_pin_and_reset_pin_flow(): void
    {
        $user = User::factory()->withPin('111111')->create(['phone' => '9123456781']);

        $forgotResponse = $this->postJson('/api/v1/auth/forgot-pin', ['phone' => '9123456781']);
        $forgotResponse->assertOk();

        $response = $this->postJson('/api/v1/auth/reset-pin', [
            'phone' => '9123456781',
            'otp' => $forgotResponse->json('data.debug_otp'),
            'pin' => '222222',
            'pin_confirmation' => '222222',
        ]);

        $response->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('222222', $user->fresh()->pin_hash));
    }

    public function test_resend_otp_respects_cooldown(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'Test User', 'phone' => '9876543212']);

        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'phone' => '9876543212',
            'purpose' => 'registration',
        ]);

        $response->assertStatus(429);
    }

    public function test_unauthenticated_request_returns_clean_json_regardless_of_accept_header(): void
    {
        // Plain get() sends no Accept header, unlike getJson(). A client that
        // omits it (many non-browser HTTP clients) must still get a JSON 401,
        // not Laravel's default redirect-to-"login" behavior (which has no
        // named route in this API-only app and previously surfaced as a 500).
        $response = $this->get('/api/v1/limits');

        $response->assertStatus(401)->assertJsonPath('success', false);
    }
}
