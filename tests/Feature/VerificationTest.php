<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_identity_verification(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/verification', [
            'passport_number' => 'P1234567',
            'passport_expiry' => now()->addYears(3)->toDateString(),
            'passport_image' => UploadedFile::fake()->image('passport.jpg'),
            'selfie_image' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('identity_verifications', ['user_id' => $user->id, 'status' => 'pending']);
    }

    public function test_cannot_submit_while_a_pending_request_exists(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'passport_number' => 'P1234567',
            'passport_expiry' => now()->addYears(3)->toDateString(),
            'passport_image' => UploadedFile::fake()->image('passport.jpg'),
            'selfie_image' => UploadedFile::fake()->image('selfie.jpg'),
        ];

        $this->postJson('/api/v1/verification', $payload)->assertCreated();
        $this->postJson('/api/v1/verification', $payload)->assertStatus(422);
    }

    public function test_approval_upgrades_tier_and_activates_virtual_card(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $admin = User::factory()->create();
        Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin->assignRole('super-admin');

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/verification', [
            'passport_number' => 'P1234567',
            'passport_expiry' => now()->addYears(3)->toDateString(),
            'passport_image' => UploadedFile::fake()->image('passport.jpg'),
            'selfie_image' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertCreated();

        $verification = \App\Models\IdentityVerification::where('user_id', $user->id)->first();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/verifications/{$verification->id}/approve")->assertOk();

        $this->assertEquals('verified', $user->fresh()->tier->value);
        $this->assertDatabaseHas('virtual_cards', ['user_id' => $user->id, 'status' => 'active']);
    }
}
