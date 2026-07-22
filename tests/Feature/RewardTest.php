<?php

namespace Tests\Feature;

use App\Models\ScratchCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_scratching_reveals_the_reward(): void
    {
        $user = User::factory()->create();
        $card = ScratchCard::create([
            'user_id' => $user->id,
            'reward_type' => 'cashback',
            'reward_value' => 10,
            'expires_at' => now()->addDays(30),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/rewards/{$card->id}/scratch");

        $response->assertOk()->assertJsonPath('data.is_scratched', true);
    }

    public function test_redeeming_a_cashback_card_credits_the_wallet(): void
    {
        $user = User::factory()->create();
        $card = ScratchCard::create([
            'user_id' => $user->id,
            'reward_type' => 'cashback',
            'reward_value' => 25,
            'is_scratched' => true,
            'scratched_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/rewards/{$card->id}/redeem")->assertOk();

        $this->assertEquals(25, (float) $user->wallet->fresh()->balance);
    }

    public function test_cannot_redeem_before_scratching(): void
    {
        $user = User::factory()->create();
        $card = ScratchCard::create([
            'user_id' => $user->id,
            'reward_type' => 'points',
            'reward_value' => 10,
            'expires_at' => now()->addDays(30),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/rewards/{$card->id}/redeem")->assertStatus(422);
    }

    public function test_user_cannot_scratch_someone_elses_card(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $card = ScratchCard::create([
            'user_id' => $owner->id,
            'reward_type' => 'points',
            'reward_value' => 10,
            'expires_at' => now()->addDays(30),
        ]);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/v1/rewards/{$card->id}/scratch")->assertStatus(403);
    }
}
