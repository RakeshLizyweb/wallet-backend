<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        return $admin;
    }

    public function test_users_can_be_filtered_by_nationality(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        User::factory()->create(['nationality' => 'Ivory Coast']);
        User::factory()->create(['nationality' => 'France']);

        $response = $this->getJson('/api/v1/admin/users?nationality=Ivory Coast');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('nationality');
        $this->assertTrue($names->every(fn ($n) => $n === 'Ivory Coast'));
        $this->assertGreaterThanOrEqual(1, $names->count());
    }

    public function test_users_can_be_filtered_by_phone_search(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        User::factory()->create(['phone' => '9876501234']);
        User::factory()->create(['phone' => '9111222333']);

        $response = $this->getJson('/api/v1/admin/users?search=98765');

        $response->assertOk();
        $phones = collect($response->json('data'))->pluck('phone');
        $this->assertTrue($phones->contains('9876501234'));
        $this->assertFalse($phones->contains('9111222333'));
    }

    public function test_wallets_can_be_searched_by_owner_phone(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $match = User::factory()->create(['phone' => '9876501234']);
        User::factory()->create(['phone' => '9111222333']);

        $response = $this->getJson('/api/v1/admin/wallets?search=98765');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('user.id');
        $this->assertTrue($ids->contains($match->id));
        $this->assertCount(1, $ids);
    }

    public function test_transactions_can_be_searched_by_sender_or_receiver_phone(): void
    {
        $admin = $this->makeAdmin();
        $sender = User::factory()->withPin('111111')->create(['phone' => '9876501234']);
        $receiver = User::factory()->create(['phone' => '9111222333']);
        $other = User::factory()->withPin('111111')->create(['phone' => '9000000000']);
        $otherReceiver = User::factory()->create();

        app(WalletService::class)->credit($sender->wallet, 1000, \App\Enums\LedgerCategory::Reward);
        app(WalletService::class)->credit($other->wallet, 1000, \App\Enums\LedgerCategory::Reward);

        Sanctum::actingAs($sender);
        $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $receiver->phone, 'amount' => 100, 'pin' => '111111',
        ])->assertCreated();

        Sanctum::actingAs($other);
        $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $otherReceiver->phone, 'amount' => 50, 'pin' => '111111',
        ])->assertCreated();

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/admin/transactions?search=98765');

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference_number');
        $this->assertCount(1, $refs);
    }

    protected function makeVerification(User $user): \App\Models\IdentityVerification
    {
        return \App\Models\IdentityVerification::create([
            'user_id' => $user->id,
            'document_type' => 'passport',
            'document_number' => 'P1234567',
            'document_expiry' => now()->addYears(3),
            'document_image_path' => 'verifications/test/doc.jpg',
            'selfie_image_path' => 'verifications/test/selfie.jpg',
            'status' => 'pending',
        ]);
    }

    public function test_verifications_can_be_searched_by_phone(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['phone' => '9876501234']);
        $this->makeVerification($user);

        $otherUser = User::factory()->create(['phone' => '9111222333']);
        $this->makeVerification($otherUser);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/admin/verifications?search=98765');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_bank_accounts_can_be_searched_by_owner_phone(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['phone' => '9876501234']);
        BankAccount::factory()->for($user)->create();

        $otherUser = User::factory()->create(['phone' => '9111222333']);
        BankAccount::factory()->for($otherUser)->create();

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/admin/banks?search=98765');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
