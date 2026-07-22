<?php

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_set_pin_hashes_the_pin(): void
    {
        $user = User::factory()->create();
        $service = app(PinService::class);

        $service->forceSetPin($user, '123456');

        $this->assertNotEquals('123456', $user->fresh()->pin_hash);
        $this->assertTrue($service->verifyPin($user->fresh(), '123456'));
    }

    public function test_verify_pin_throws_for_wrong_pin(): void
    {
        $user = User::factory()->withPin('123456')->create();
        $service = app(PinService::class);

        $this->expectException(ApiException::class);
        $service->verifyPin($user, '999999');
    }

    public function test_set_pin_fails_if_already_set(): void
    {
        $user = User::factory()->withPin('123456')->create();
        $service = app(PinService::class);

        $this->expectException(ApiException::class);
        $service->setPin($user, '654321');
    }
}
