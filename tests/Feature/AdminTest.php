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
            ->assertJsonStructure(['data' => ['users', 'wallets', 'transactions', 'fees', 'verifications', 'rewards']]);
    }

    public function test_dashboard_reports_fees_collected_from_successful_transfers_only(): void
    {
        $sender = User::factory()->withPin('123456')->create();
        $receiver = User::factory()->create();
        app(\App\Services\WalletService::class)->credit($sender->wallet, 1000, \App\Enums\LedgerCategory::Reward);

        Sanctum::actingAs($sender);
        $reference = $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $receiver->phone, 'amount' => 100, 'pin' => '123456',
        ])->json('data.reference_number');

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->assertEquals(1.0, $response->json('data.fees.today'));
        $this->assertEquals(1.0, $response->json('data.fees.total'));

        // Reversed transfers must not count as collected fee revenue.
        $this->postJson("/api/v1/admin/transactions/{$reference}/reverse", ['reason' => 'test'])->assertOk();

        $response = $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->assertEquals(0.0, $response->json('data.fees.today'));
        $this->assertEquals(0.0, $response->json('data.fees.total'));
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

    public function test_admin_can_create_a_user(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $response = $this->postJson('/api/v1/admin/users', [
            'name' => 'Walk-in Customer',
            'phone' => '9812345678',
            'nationality' => 'Ivory Coast',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Walk-in Customer')
            ->assertJsonPath('data.phone', '9812345678')
            ->assertJsonPath('data.phone_verified', true);

        $user = User::where('phone', '9812345678')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->wallet);
        $this->assertNotNull($user->account);

        // Admin-created users are phone-verified immediately, so they can
        // request a login OTP right away without going through registration.
        $login = $this->postJson('/api/v1/auth/login', ['phone' => '9812345678']);
        $login->assertOk();
    }

    public function test_admin_cannot_create_a_user_with_a_duplicate_phone(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $existing = User::factory()->create(['phone' => '9812345678']);

        $response = $this->postJson('/api/v1/admin/users', [
            'name' => 'Someone Else',
            'phone' => '9812345678',
            'nationality' => 'India',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_adjust_defaults_to_crediting_the_account_not_the_wallet(): void
    {
        $admin = $this->makeAdmin();
        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/admin/wallets/{$target->wallet->id}/adjust", [
            'type' => 'credit',
            'amount' => 250,
            'reason' => 'Goodwill credit',
        ])->assertOk();

        // Wallet balance is untouched...
        $response->assertJsonPath('data.balance', 0);
        // ...the credit landed in the user's Account instead.
        $this->assertEquals(250, $target->account->fresh()->balance);
    }

    public function test_admin_can_explicitly_adjust_the_wallet_bucket(): void
    {
        $admin = $this->makeAdmin();
        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/wallets/{$target->wallet->id}/adjust", [
            'type' => 'credit',
            'bucket' => 'wallet',
            'amount' => 250,
            'reason' => 'Goodwill credit',
        ])->assertOk()->assertJsonPath('data.balance', 250);

        $this->assertEquals(0, $target->account->fresh()->balance);
    }
}
