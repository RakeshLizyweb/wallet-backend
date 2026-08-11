<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    protected function registerAndVerify(string $phone, ?string $referralCode = null): User
    {
        $payload = ['name' => 'New User', 'phone' => $phone, 'nationality' => 'Ivory Coast'];
        if ($referralCode) {
            $payload['referral_code'] = $referralCode;
        }

        $register = $this->postJson('/api/v1/auth/register', $payload)->assertOk();
        $otp = $register->json('data.debug_otp');

        $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $phone, 'otp' => $otp, 'purpose' => 'registration',
        ])->assertOk();

        return User::where('phone', $phone)->firstOrFail();
    }

    public function test_registering_with_a_valid_referral_code_links_the_referrer(): void
    {
        $referrer = User::factory()->create();
        $code = app(\App\Services\ReferralService::class)->getOrCreateCodeForUser($referrer);

        $newUser = $this->registerAndVerify('9700000001', $code);

        $this->assertEquals($referrer->id, $newUser->referred_by_id);
        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $referrer->id,
            'referred_user_id' => $newUser->id,
            'referred_phone' => '9700000001',
        ]);
    }

    public function test_registering_with_an_unknown_referral_code_is_ignored_not_blocked(): void
    {
        $newUser = $this->registerAndVerify('9700000002', 'BADCODE1');

        $this->assertNull($newUser->referred_by_id);
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_a_user_cannot_refer_themselves(): void
    {
        $referralService = app(\App\Services\ReferralService::class);
        $userRepo = app(\App\Repositories\Contracts\UserRepositoryInterface::class);

        $user = User::factory()->unverified()->create(['phone' => '9700000003']);
        $code = $userRepo->generateUniqueReferralCode();
        $user->update(['referral_code' => $code]);

        $referralService->redeem($user, $code);

        $this->assertNull($user->fresh()->referred_by_id);
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_referral_bonus_is_paid_after_referred_users_first_qualifying_transfer(): void
    {
        $referrer = User::factory()->create();
        $referralService = app(\App\Services\ReferralService::class);
        $code = $referralService->getOrCreateCodeForUser($referrer);

        $newUser = $this->registerAndVerify('9700000004', $code);
        $newUser->update(['pin_hash' => bcrypt('123456'), 'pin_set_at' => now()]);
        app(WalletService::class)->credit($newUser->wallet, 1000, \App\Enums\LedgerCategory::Reward);

        $receiver = User::factory()->create();

        Sanctum::actingAs($newUser->fresh());
        $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $receiver->upi_handle, 'amount' => 100, 'pin' => '123456',
        ])->assertCreated();

        $this->assertEquals(100, (float) $referrer->account->fresh()->balance);
        $this->assertEquals(300, (float) $newUser->account->fresh()->balance);

        $referral = Referral::where('referred_user_id', $newUser->id)->firstOrFail();
        $this->assertNotNull($referral->rewarded_at);
    }

    public function test_referral_bonus_is_not_paid_twice_on_a_second_transfer(): void
    {
        $referrer = User::factory()->create();
        $referralService = app(\App\Services\ReferralService::class);
        $code = $referralService->getOrCreateCodeForUser($referrer);

        $newUser = $this->registerAndVerify('9700000005', $code);
        $newUser->update(['pin_hash' => bcrypt('123456'), 'pin_set_at' => now()]);
        app(WalletService::class)->credit($newUser->wallet, 1000, \App\Enums\LedgerCategory::Reward);

        $receiver = User::factory()->create();
        Sanctum::actingAs($newUser->fresh());

        $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $receiver->upi_handle, 'amount' => 100, 'pin' => '123456',
        ])->assertCreated();
        $this->postJson('/api/v1/transfers/wallet-to-wallet', [
            'receiver' => $receiver->upi_handle, 'amount' => 50, 'pin' => '123456',
        ])->assertCreated();

        // Only the first transfer's bonus should have landed - not doubled.
        $this->assertEquals(100, (float) $referrer->account->fresh()->balance);
        $this->assertEquals(300, (float) $newUser->account->fresh()->balance);
    }

    public function test_self_transfers_do_not_count_as_the_qualifying_first_transaction(): void
    {
        $referrer = User::factory()->create();
        $referralService = app(\App\Services\ReferralService::class);
        $code = $referralService->getOrCreateCodeForUser($referrer);

        $newUser = $this->registerAndVerify('9700000006', $code);
        $newUser->update(['pin_hash' => bcrypt('123456'), 'pin_set_at' => now()]);
        app(WalletService::class)->credit($newUser->wallet, 1000, \App\Enums\LedgerCategory::Reward);

        Sanctum::actingAs($newUser->fresh());
        // A self-move between the user's own Wallet and Account shouldn't trigger the bonus.
        $this->postJson('/api/v1/transfers/wallet-to-account', ['amount' => 100, 'pin' => '123456'])->assertCreated();

        $this->assertEquals(0, (float) $referrer->account->fresh()->balance);

        $referral = Referral::where('referred_user_id', $newUser->id)->firstOrFail();
        $this->assertNull($referral->rewarded_at);
    }

    public function test_deleting_account_and_reregistering_with_a_different_referral_code_does_not_work(): void
    {
        $referrerA = User::factory()->create();
        $referrerB = User::factory()->create();
        $referralService = app(\App\Services\ReferralService::class);
        $codeA = $referralService->getOrCreateCodeForUser($referrerA);
        $codeB = $referralService->getOrCreateCodeForUser($referrerB);

        $phone = '9700000007';
        $firstUser = $this->registerAndVerify($phone, $codeA);
        $this->assertEquals($referrerA->id, $firstUser->referred_by_id);

        // Delete the account (requires a PIN, per AuthService::deleteAccount).
        $firstUser->update(['pin_hash' => bcrypt('123456'), 'pin_set_at' => now()]);
        Sanctum::actingAs($firstUser->fresh());
        $this->deleteJson('/api/v1/auth/account', ['pin' => '123456'])->assertOk();

        // The original phone is now free (AuthService mangles it on delete),
        // so this is a genuinely new users row re-registering the same phone.
        // Travel past the OTP resend cooldown, which is keyed by phone number
        // and would otherwise still be active from the first registration.
        $this->travel(config('wallet.otp.resend_cooldown_seconds') + 1)->seconds();
        $secondUser = $this->registerAndVerify($phone, $codeB);

        $this->assertNull($secondUser->referred_by_id);
        $this->assertDatabaseCount('referrals', 1);
        $this->assertDatabaseHas('referrals', ['referrer_id' => $referrerA->id, 'referred_phone' => $phone]);
        $this->assertDatabaseMissing('referrals', ['referrer_id' => $referrerB->id]);
    }

    public function test_referral_summary_returns_code_and_stats(): void
    {
        $referrer = User::factory()->create();
        Sanctum::actingAs($referrer);

        $response = $this->getJson('/api/v1/referrals/summary')->assertOk();

        $this->assertNotNull($response->json('data.code'));
        $this->assertEquals(100, $response->json('data.referrer_bonus'));
        $this->assertEquals(300, $response->json('data.referred_bonus'));
        $this->assertEquals(0, $response->json('data.total_referred'));
    }
}
