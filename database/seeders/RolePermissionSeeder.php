<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    protected array $permissions = [
        'users.manage',
        'wallets.manage',
        'transactions.manage',
        'verifications.manage',
        'banks.manage',
        'rewards.manage',
        'notifications.manage',
        'settings.manage',
        'roles.manage',
        'reports.view',
        'logs.view',
    ];

    public function run(): void
    {
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($this->permissions);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
            'users.manage', 'wallets.manage', 'transactions.manage', 'verifications.manage',
            'banks.manage', 'rewards.manage', 'notifications.manage', 'reports.view',
        ]);

        $support = Role::firstOrCreate(['name' => 'support', 'guard_name' => 'web']);
        $support->syncPermissions([
            'verifications.manage', 'banks.manage', 'notifications.manage', 'reports.view',
        ]);
    }
}
