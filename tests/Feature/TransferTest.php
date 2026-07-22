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

        $response->assertCreated()->assertJsonPath('data.status', 'success');

        $this->assertEquals(700, (float) $sender->wallet->fresh()->balance);
        $this->assertEquals(300, (float) $receiver->wallet->fresh()->balance);
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

    public function test_transfer_exceeding_daily_limit_is_rejected(): void
    {
        $sender = User::factory()->withPin('123456')->create();
        $receiver = User::factory()->create();
        app(WalletService::class)->credit($sender->wallet, 100000, LedgerCategory::Reward);
        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $receiver->upi_handle,
            'amount' => 30000, // basic tier daily limit is 25000
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

        app(\App\Services\TransferService::class)->reverse(
            app(\App\Services\TransferService::class)->findByReference($reference),
            'test reversal'
        );

        $this->assertEquals(1000, (float) $sender->wallet->fresh()->balance);
        $this->assertEquals(0, (float) $receiver->wallet->fresh()->balance);
    }
}
