<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'manage listings', 'moderate listings',
            'manage products',
            'manage categories',
            'manage plans', 'manage payments',
            'manage users',
            'manage settings', 'manage content',
            'moderate reviews', 'import data',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions($permissions);

        $staff = Role::findOrCreate('staff', 'web');
        $staff->syncPermissions(['moderate listings', 'moderate reviews', 'manage content']);

        // Members hold no admin permissions; their access is owner-scoped via policies.
        Role::findOrCreate('member', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
