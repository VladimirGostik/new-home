<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

final class PermissionSeeder extends Seeder
{
    public const array PERMISSIONS = [
        'view users',
        'create users',
        'edit users',
        'delete users',
        'view roles',
        'create roles',
        'edit roles',
        'delete roles',
        'view audit logs',
        'edit global settings',
        'view api docs',
        'view media',
        'upload files',
        'view rooms',
        'create rooms',
        'edit rooms',
        'delete rooms',
        'view items',
        'create items',
        'edit items',
        'delete items',
    ];

    /** Every family member (role `user`) can see and edit the whole plan and upload photos. */
    public const array FAMILY_PERMISSIONS = [
        'upload files',
        'view rooms',
        'create rooms',
        'edit rooms',
        'delete rooms',
        'view items',
        'create items',
        'edit items',
        'delete items',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(self::PERMISSIONS);

        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $userRole->syncPermissions(self::FAMILY_PERMISSIONS);
    }
}
