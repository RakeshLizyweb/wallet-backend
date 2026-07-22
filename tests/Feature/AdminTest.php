<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        return $admin;
    }

    public function test_regular_user_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }

    public function test_admin_can_view_dashboard(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['users', 'wallets', 'transactions', 'verifications', 'rewards']]);
    }

    public function test_admin_can_list_and_update_users(): void
    {
        $admin = $this->makeAdmin();
        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/users')->assertOk();

        $this->putJson("/api/v1/admin/users/{$target->id}/status", ['status' => 'deactivated'])
            ->assertOk()
            ->assertJsonPath('data.status', 'deactivated');
    }

    public function test_admin_can_adjust_wallet_balance(): void
    {
        $admin = $this->makeAdmin();
        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/wallets/{$target->wallet->id}/adjust", [
            'type' => 'credit',
            'amount' => 250,
            'reason' => 'Goodwill credit',
        ])->assertOk()->assertJsonPath('data.balance', 250);
    }
}
