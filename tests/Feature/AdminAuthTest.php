<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAdmin(string $username, string $password): User
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['username' => $username, 'password' => $password]);
        $admin->assignRole('super-admin');

        return $admin;
    }

    public function test_admin_can_login_with_username_and_password(): void
    {
        $this->makeAdmin('testadmin', 'Sup3rSecret!');

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'username' => 'testadmin',
            'password' => 'Sup3rSecret!',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.username', 'testadmin')
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->makeAdmin('testadmin', 'Sup3rSecret!');

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'username' => 'testadmin',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_unknown_username_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/admin/auth/login', [
            'username' => 'doesnotexist',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
    }

    public function test_non_admin_user_with_credentials_cannot_login(): void
    {
        User::factory()->create(['username' => 'regularuser', 'password' => 'Sup3rSecret!']);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'username' => 'regularuser',
            'password' => 'Sup3rSecret!',
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_set_credentials_for_another_user(): void
    {
        $admin = $this->makeAdmin('rootadmin', 'RootPass1!');
        $target = User::factory()->create();
        Role::firstOrCreate(['name' => 'support', 'guard_name' => 'web']);
        $target->assignRole('support');

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/users/{$target->id}/credentials", [
            'username' => 'new_admin',
            'password' => 'NewPass123!',
        ]);

        $response->assertOk()->assertJsonPath('data.username', 'new_admin');

        $login = $this->postJson('/api/v1/admin/auth/login', [
            'username' => 'new_admin',
            'password' => 'NewPass123!',
        ]);

        $login->assertOk();
    }

    public function test_username_must_be_unique(): void
    {
        $admin = $this->makeAdmin('rootadmin2', 'RootPass1!');
        User::factory()->create(['username' => 'taken']);
        $target = User::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/users/{$target->id}/credentials", [
            'username' => 'taken',
            'password' => 'NewPass123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_reset_forgotten_password_via_otp(): void
    {
        $this->makeAdmin('forgetful', 'OldPass1!');

        $forgot = $this->postJson('/api/v1/admin/auth/forgot-password', [
            'username' => 'forgetful',
        ]);

        $forgot->assertOk()->assertJsonStructure(['data' => ['masked_phone', 'debug_otp']]);

        $otp = $forgot->json('data.debug_otp');

        $reset = $this->postJson('/api/v1/admin/auth/reset-password', [
            'username' => 'forgetful',
            'otp' => $otp,
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);

        $reset->assertOk();

        $login = $this->postJson('/api/v1/admin/auth/login', [
            'username' => 'forgetful',
            'password' => 'NewPass123!',
        ]);

        $login->assertOk();
    }

    public function test_forgot_password_rejects_unknown_username(): void
    {
        $response = $this->postJson('/api/v1/admin/auth/forgot-password', [
            'username' => 'doesnotexist',
        ]);

        $response->assertStatus(404);
    }

    public function test_reset_password_rejects_wrong_otp(): void
    {
        $this->makeAdmin('forgetful2', 'OldPass1!');

        $this->postJson('/api/v1/admin/auth/forgot-password', ['username' => 'forgetful2']);

        $response = $this->postJson('/api/v1/admin/auth/reset-password', [
            'username' => 'forgetful2',
            'otp' => '000000',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);

        $response->assertStatus(422);
    }
}
