<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_is_created_automatically_on_registration(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('accounts', ['user_id' => $user->id]);
    }

    public function test_account_balance_requires_pin_header(): void
    {
        $user = User::factory()->withPin('111111')->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/account')->assertStatus(422);
    }

    public function test_account_to_account_transfer_moves_balance_between_users(): void
    {
        $sender = User::factory()->withPin('111111')->create();
        $receiver = User::factory()->create();

        $sender->account->update(['balance' => 1000]);

        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/v1/transfers/account-to-account', [
            'receiver' => $receiver->phone,
            'amount' => 300,
            'pin' => '111111',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'account_to_account')
            ->assertJsonPath('data.fee', 3);

        // Sender pays exactly the amount they typed — the 1% platform fee
        // comes out of what the receiver gets, not on top of what the
        // sender pays.
        $this->assertEquals(700, $sender->account->fresh()->balance);
        $this->assertEquals(297, $receiver->account->fresh()->balance);
        // The Wallet side must be completely untouched by an Account payment.
        $this->assertEquals(0, $sender->wallet->fresh()->balance);
        $this->assertEquals(0, $receiver->wallet->fresh()->balance);
    }

    public function test_account_to_account_insufficient_balance_is_rejected(): void
    {
        $sender = User::factory()->withPin('111111')->create();
        $receiver = User::factory()->create();

        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/v1/transfers/account-to-account', [
            'receiver' => $receiver->phone,
            'amount' => 300,
            'pin' => '111111',
        ]);

        $response->assertStatus(422);
    }

    public function test_account_to_wallet_moves_own_money_between_own_buckets(): void
    {
        $user = User::factory()->withPin('111111')->create();
        $user->account->update(['balance' => 500]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/transfers/account-to-wallet', [
            'amount' => 200,
            'pin' => '111111',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'account_to_wallet')
            ->assertJsonPath('data.direction', 'internal');

        $this->assertEquals(300, $user->account->fresh()->balance);
        $this->assertEquals(200, $user->wallet->fresh()->balance);
    }

    public function test_account_to_wallet_is_not_subject_to_the_outbound_transfer_limit(): void
    {
        // Basic tier monthly limit is 150000 by default; account_to_wallet is
        // a self-move and must not count against it, so this should succeed
        // even though it's larger than the configured monthly cap.
        $user = User::factory()->withPin('111111')->create();
        $user->account->update(['balance' => 200000]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transfers/account-to-wallet', [
            'amount' => 160000,
            'pin' => '111111',
        ])->assertCreated();
    }

    public function test_account_to_account_exceeding_monthly_limit_is_rejected(): void
    {
        $sender = User::factory()->withPin('111111')->create();
        $receiver = User::factory()->create();
        $sender->account->update(['balance' => 200000]);

        Sanctum::actingAs($sender);

        $this->postJson('/api/v1/transfers/account-to-account', [
            'receiver' => $receiver->phone,
            'amount' => 160000, // exceeds the default basic-tier monthly limit of 150000
            'pin' => '111111',
        ])->assertStatus(422);
    }

    public function test_cannot_send_account_to_account_to_self(): void
    {
        $user = User::factory()->withPin('111111')->create();
        $user->account->update(['balance' => 1000]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transfers/account-to-account', [
            'receiver' => $user->phone,
            'amount' => 100,
            'pin' => '111111',
        ])->assertStatus(422);
    }

    public function test_admin_can_reverse_an_account_to_account_transfer(): void
    {
        $sender = User::factory()->withPin('111111')->create();
        $receiver = User::factory()->create();
        $sender->account->update(['balance' => 1000]);

        Sanctum::actingAs($sender);
        $response = $this->postJson('/api/v1/transfers/account-to-account', [
            'receiver' => $receiver->phone,
            'amount' => 300,
            'pin' => '111111',
        ])->assertCreated();

        $reference = $response->json('data.reference_number');

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/transactions/{$reference}/reverse", [
            'reason' => 'Sent to the wrong person',
        ])->assertOk();

        $this->assertEquals(1000, $sender->account->fresh()->balance);
        $this->assertEquals(0, $receiver->account->fresh()->balance);
    }

    public function test_user_can_search_other_users_by_phone(): void
    {
        $user = User::factory()->create();
        $match = User::factory()->create(['phone' => '9876501234', 'name' => 'Findable Friend']);
        User::factory()->create(['phone' => '9111222333']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/users/search?phone=98765');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Findable Friend'));
        $this->assertCount(1, $response->json('data'));
    }

    public function test_user_search_excludes_self(): void
    {
        $user = User::factory()->create(['phone' => '9876501234']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/users/search?phone=98765');

        $response->assertOk()->assertJsonCount(0, 'data');
    }
}
