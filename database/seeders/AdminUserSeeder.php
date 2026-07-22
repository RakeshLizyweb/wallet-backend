<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Enums\UserTier;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['phone' => '9999999999'],
            [
                'name' => 'Super Admin',
                'phone_verified_at' => now(),
                'upi_handle' => '9999999999@wallet',
                'username' => 'superadmin',
                'password' => 'ChangeMe123!',
                'status' => UserStatus::Active->value,
                'tier' => UserTier::Premium->value,
                'pin_hash' => '123456',
                'pin_set_at' => now(),
            ]
        );

        if (! $admin->username || ! $admin->password) {
            $admin->update(['username' => 'superadmin', 'password' => 'ChangeMe123!']);
        }

        $admin->syncRoles(['super-admin']);
    }
}
