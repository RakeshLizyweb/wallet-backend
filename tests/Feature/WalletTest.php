<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_requires_pin_header(): void
    {
        $user = User::factory()->withPin('123456')->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/wallet')->assertStatus(422);

        $this->withHeaders(['X-Pin' => '123456'])
            ->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonStructure(['data' => ['wallet_number', 'balance', 'frozen_balance', 'available_balance']]);
    }

    public function test_wrong_pin_is_rejected(): void
    {
        $user = User::factory()->withPin('123456')->create();
        Sanctum::actingAs($user);

        $this->withHeaders(['X-Pin' => '000000'])
            ->getJson('/api/v1/wallet')
            ->assertStatus(422);
    }

    public function test_mini_statement_returns_recent_transactions(): void
    {
        $user = User::factory()->withPin('123456')->create();
        Sanctum::actingAs($user);

        app(\App\Services\WalletService::class)->credit(
            $user->wallet,
            100,
            \App\Enums\LedgerCategory::Reward,
        );

        $response = $this->getJson('/api/v1/wallet/statement/mini');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
