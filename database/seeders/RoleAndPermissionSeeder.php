<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'access admin panel',
            'manage patients',
            'manage staff',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::findOrCreate('admin', 'web');
        $staffRole = Role::findOrCreate('staff', 'web');

        $adminRole->syncPermissions($permissions);
        $staffRole->syncPermissions([
            'access admin panel',
            'manage patients',
        ]);
    }
}
