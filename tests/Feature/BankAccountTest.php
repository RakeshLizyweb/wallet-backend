<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_bank_account_becomes_primary_automatically(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/banks', [
            'bank_name' => 'HDFC Bank',
            'account_holder_name' => 'Test User',
            'account_number' => '123456789012',
            'ifsc_code' => 'HDFC0001234',
        ]);

        $response->assertCreated()->assertJsonPath('data.is_primary', true);
    }

    public function test_duplicate_account_number_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'bank_name' => 'HDFC Bank',
            'account_holder_name' => 'Test User',
            'account_number' => '123456789012',
            'ifsc_code' => 'HDFC0001234',
        ];

        $this->postJson('/api/v1/banks', $payload)->assertCreated();
        $this->postJson('/api/v1/banks', $payload)->assertStatus(422);
    }

    public function test_deleting_primary_account_promotes_another(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $first = $this->postJson('/api/v1/banks', [
            'bank_name' => 'HDFC Bank', 'account_holder_name' => 'Test User',
            'account_number' => '111111111111', 'ifsc_code' => 'HDFC0001234',
        ])->json('data');

        $second = $this->postJson('/api/v1/banks', [
            'bank_name' => 'ICICI Bank', 'account_holder_name' => 'Test User',
            'account_number' => '222222222222', 'ifsc_code' => 'ICIC0005678',
        ])->json('data');

        $this->deleteJson("/api/v1/banks/{$first['id']}")->assertOk();

        $this->assertDatabaseHas('bank_accounts', ['id' => $second['id'], 'is_primary' => true]);
    }

    public function test_user_cannot_modify_another_users_bank_account(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        Sanctum::actingAs($owner);
        $account = $this->postJson('/api/v1/banks', [
            'bank_name' => 'HDFC Bank', 'account_holder_name' => 'Owner',
            'account_number' => '333333333333', 'ifsc_code' => 'HDFC0001234',
        ])->json('data');

        Sanctum::actingAs($intruder);
        $this->putJson("/api/v1/banks/{$account['id']}", ['bank_name' => 'Hacked'])
            ->assertStatus(403);
    }
}
