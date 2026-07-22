<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Enums\UserTier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '9'.fake()->unique()->numerify('#########');

        return [
            'name' => fake()->name(),
            'phone' => $phone,
            'phone_verified_at' => now(),
            'upi_handle' => "{$phone}@wallet",
            'status' => UserStatus::Active->value,
            'tier' => UserTier::Basic->value,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's phone number should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has already set up a PIN.
     */
    public function withPin(string $pin = '123456'): static
    {
        return $this->state(fn (array $attributes) => [
            'pin_hash' => $pin,
            'pin_set_at' => now(),
        ]);
    }
}
