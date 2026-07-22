<?php

namespace Tests\Unit;

use App\Enums\LedgerCategory;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\LimitService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimitServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_tier_limits_match_config(): void
    {
        $user = User::factory()->create(['tier' => 'basic']);

        $limits = app(LimitService::class)->limitsFor($user);

        $this->assertEquals(config('wallet.limits.basic'), $limits);
    }

    public function test_premium_tier_has_no_limit(): void
    {
        $user = User::factory()->create(['tier' => 'premium']);

        app(LimitService::class)->assertWithinLimits($user, 99999999);

        $this->expectNotToPerformAssertions();
    }

    public function test_exceeding_daily_limit_throws(): void
    {
        $user = User::factory()->create(['tier' => 'basic']);

        $this->expectException(ApiException::class);

        app(LimitService::class)->assertWithinLimits($user, config('wallet.limits.basic.daily') + 1);
    }

    public function test_usage_accumulates_across_transfers(): void
    {
        $sender = User::factory()->withPin('123456')->create(['tier' => 'basic']);
        $receiver = User::factory()->create();

        app(WalletService::class)->credit($sender->wallet, 10000, LedgerCategory::Reward);

        app(\App\Services\TransferService::class)->walletToWallet(
            $sender, $receiver->upi_handle, 1000, '123456'
        );

        $usage = app(LimitService::class)->usageFor($sender);

        $this->assertEquals(1000, $usage['daily']);
    }
}
