<?php

namespace Tests\Feature;

use App\Enums\LedgerCategory;
use App\Models\BankAccount;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_to_wallet_transfer_moves_balance_between_users(): void
    {
        $sender = User::factory()->withPin('123456')->create();
        $receiver = User::factory()->create();

        app(WalletService::class)->credit($sender->wallet, 1000, LedgerCategory::Reward);

        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $receiver->upi_handle,
            'amount' => 300,
            'pin' => '123456',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.fee', 3)
            ->assertJsonPath('data.amount', 300);

        // Sender pays exactly the amount they typed — the 1% platform fee
        // comes out of what the receiver gets, not on top of what the
        // sender pays.
        $this->assertEquals(700, (float) $sender->wallet->fresh()->balance);
        // Sender pays from their Wallet, but the receiver always gets it in
        // their Account — Account is the single inbox for incoming money,
        // Wallet is a spending pocket each user tops up for themselves.
        $this->assertEquals(297, (float) $receiver->account->fresh()->balance);
        $this->assertEquals(0, (float) $receiver->wallet->fresh()->balance);
    }

    public function test_cannot_send_money_to_self(): void
    {
        $user = User::factory()->withPin('123456')->create();
        app(WalletService::class)->credit($user->wallet, 1000, LedgerCategory::Reward);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $user->upi_handle,
            'amount' => 100,
            'pin' => '123456',
        ])->assertStatus(422);
    }

    public function test_insufficient_balance_is_rejected(): void
    {
        $sender = User::factory()->withPin('123456')->create();
        $receiver = User::factory()->create();
        Sanctum::actingAs($sender);

        $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $receiver->upi_handle,
            'amount' => 500,
            'pin' => '123456',
        ])->assertStatus(422);
    }

    public function test_transfer_exceeding_monthly_limit_is_rejected(): void
    {
        $sender = User::factory()->withPin('123456')->create();
        $receiver = User::factory()->create();
        app(WalletService::class)->credit($sender->wallet, 200000, LedgerCategory::Reward);
        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $receiver->upi_handle,
            'amount' => 160000, // basic tier monthly limit is 150000
            'pin' => '123456',
        ]);

        $response->assertStatus(422);
    }

    public function test_wallet_to_bank_requires_verified_bank_account(): void
    {
        $user = User::factory()->withPin('123456')->create();
        app(WalletService::class)->credit($user->wallet, 1000, LedgerCategory::Reward);

        $bank = BankAccount::factory()->for($user)->create(['is_verified' => false]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transfers/wallet-to-bank', [
            'bank_account_id' => $bank->id,
            'amount' => 100,
            'pin' => '123456',
        ])->assertStatus(422);
    }

    public function test_bank_to_wallet_credits_wallet(): void
    {
        $user = User::factory()->create();
        $bank = BankAccount::factory()->for($user)->create(['is_verified' => true]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/transfers/bank-to-wallet', [
            'bank_account_id' => $bank->id,
            'amount' => 500,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.direction', 'credit');
        $this->assertEquals(500, (float) $user->wallet->fresh()->balance);
    }

    public function test_wallet_to_bank_direction_is_debit(): void
    {
        $user = User::factory()->withPin('123456')->create();
        $bank = BankAccount::factory()->for($user)->create(['is_verified' => true]);
        app(WalletService::class)->credit($user->wallet, 1000, LedgerCategory::Reward);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/transfers/wallet-to-bank', [
            'bank_account_id' => $bank->id,
            'amount' => 200,
            'pin' => '123456',
        ]);

        $response->assertCreated()->assertJsonPath('data.direction', 'debit');
    }

    public function test_reversal_restores_balances(): void
    {
        $sender = User::factory()->withPin('123456')->create();
        $receiver = User::factory()->create();
        app(WalletService::class)->credit($sender->wallet, 1000, LedgerCategory::Reward);
        Sanctum::actingAs($sender);

        $reference = $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $receiver->upi_handle,
            'amount' => 200,
            'pin' => '123456',
        ])->json('data.reference_number');

        // The 200 landed in the receiver's Account (minus the 1% fee), not their Wallet.
        $this->assertEquals(198, (float) $receiver->account->fresh()->balance);

        app(\App\Services\TransferService::class)->reverse(
            app(\App\Services\TransferService::class)->findByReference($reference),
            'test reversal'
        );

        $this->assertEquals(1000, (float) $sender->wallet->fresh()->balance);
        $this->assertEquals(0, (float) $receiver->account->fresh()->balance);
    }

    public function test_recent_contacts_lists_distinct_peers_most_recent_first(): void
    {
        $user = User::factory()->withPin('123456')->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        app(WalletService::class)->credit($user->wallet, 1000, LedgerCategory::Reward);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $alice->upi_handle, 'amount' => 100, 'pin' => '123456',
        ])->assertCreated();
        $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $bob->upi_handle, 'amount' => 50, 'pin' => '123456',
        ])->assertCreated();
        // A second payment to Alice must not create a duplicate entry —
        // she should still appear once, moved to the front (most recent).
        $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $alice->upi_handle, 'amount' => 20, 'pin' => '123456',
        ])->assertCreated();

        $response = $this->getJson('/api/v1/transfers/recent-contacts')->assertOk();

        $response->assertJsonCount(2, 'data');
        $this->assertEquals($alice->phone, $response->json('data.0.phone'));
        $this->assertEquals($bob->phone, $response->json('data.1.phone'));
    }
}
