<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function mockCloudinaryUploads(): void
    {
        $this->mock(CloudinaryService::class, function ($mock) {
            $mock->shouldReceive('uploadPrivateImage')->andReturn('verifications/fake-public-id');
        });
    }

    public function test_user_can_submit_identity_verification(): void
    {
        $this->mockCloudinaryUploads();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/verification', [
            'document_number' => 'P1234567',
            'document_expiry' => now()->addYears(3)->toDateString(),
            'document_image' => UploadedFile::fake()->image('passport.jpg'),
            'selfie_image' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('identity_verifications', ['user_id' => $user->id, 'status' => 'pending']);
    }

    public function test_cannot_submit_while_a_pending_request_exists(): void
    {
        $this->mockCloudinaryUploads();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'document_number' => 'P1234567',
            'document_expiry' => now()->addYears(3)->toDateString(),
            'document_image' => UploadedFile::fake()->image('passport.jpg'),
            'selfie_image' => UploadedFile::fake()->image('selfie.jpg'),
        ];

        $this->postJson('/api/v1/verification', $payload)->assertCreated();
        $this->postJson('/api/v1/verification', $payload)->assertStatus(422);
    }

    public function test_ivory_coast_national_is_required_to_submit_a_citizen_id(): void
    {
        $this->mockCloudinaryUploads();

        $user = User::factory()->create(['nationality' => 'Ivory Coast']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/verification', [
            'document_number' => 'CI9988776',
            'document_expiry' => now()->addYears(3)->toDateString(),
            'document_image' => UploadedFile::fake()->image('citizen_id.jpg'),
            'selfie_image' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $response->assertCreated()->assertJsonPath('data.document_type', 'citizen_id');
        $this->assertDatabaseHas('identity_verifications', [
            'user_id' => $user->id,
            'document_type' => 'citizen_id',
            'document_number' => 'CI9988776',
        ]);
    }

    public function test_non_ivory_coast_national_submits_a_passport(): void
    {
        $this->mockCloudinaryUploads();

        $user = User::factory()->create(['nationality' => 'France']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/verification', [
            'document_number' => 'P1234567',
            'document_expiry' => now()->addYears(3)->toDateString(),
            'document_image' => UploadedFile::fake()->image('passport.jpg'),
            'selfie_image' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $response->assertCreated()->assertJsonPath('data.document_type', 'passport');
    }

    public function test_approval_upgrades_tier_and_activates_virtual_card(): void
    {
        $this->mockCloudinaryUploads();

        $user = User::factory()->create();
        $admin = User::factory()->create();
        Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin->assignRole('super-admin');

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/verification', [
            'document_number' => 'P1234567',
            'document_expiry' => now()->addYears(3)->toDateString(),
            'document_image' => UploadedFile::fake()->image('passport.jpg'),
            'selfie_image' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertCreated();

        $verification = \App\Models\IdentityVerification::where('user_id', $user->id)->first();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/verifications/{$verification->id}/approve")->assertOk();

        $this->assertEquals('verified', $user->fresh()->tier->value);
        $this->assertDatabaseHas('virtual_cards', ['user_id' => $user->id, 'status' => 'active']);
    }
}
