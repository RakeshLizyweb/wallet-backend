<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        $accountNumber = (string) fake()->unique()->numerify('############');

        return [
            'user_id' => User::factory(),
            'bank_name' => fake()->company(),
            'account_holder_name' => fake()->name(),
            'account_number_encrypted' => Crypt::encryptString($accountNumber),
            'account_number_hash' => BankAccount::hashAccountNumber($accountNumber),
            'account_number_last4' => substr($accountNumber, -4),
            'ifsc_code' => 'HDFC0001234',
            'account_type' => 'savings',
            'is_primary' => true,
            'is_verified' => false,
        ];
    }
}
