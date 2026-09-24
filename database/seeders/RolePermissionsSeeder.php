<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionsSeeder extends Seeder
{
    /**
     * Permission names are route name patterns (fnmatch-compatible).
     * Administrators bypass the check entirely — no permissions needed.
     */
    private const ROLE_PERMISSIONS = [
        'Manager' => [
            'admin.dashboard',
            'admin.tables.*',
            'admin.areas.*',
            'admin.bookings.*',
            'admin.pos.*',
            'admin.printer.*',
            'admin.transaction-history.*',
            'admin.recap.*',
            'admin.transaction-checker.*',
            'admin.kitchen.*',
            'admin.bar.*',
            'admin.inventory.*',
            'admin.bom.*',
            'admin.menus.*',
            'admin.stock-opname.*',
            'admin.customers.*',
            'admin.customer-keep.*',
            'admin.rewards.*',
            'admin.song-requests.*',
            'admin.display-messages.*',
            'admin.events.*',
            'admin.promos.*',
            'admin.waiter-performance.*',
            'admin.settings.*',
            'admin.accurate.*',
            'admin.sync.*',
        ],
        'Cashier' => [
            'admin.dashboard',
            'admin.pos.*',
            'admin.printer.*',
            'admin.bookings.*',
            'admin.transaction-history.*',
            'admin.recap.*',
            'admin.transaction-checker.*',
            'admin.customers.*',
            'admin.customer-keep.*',
            'admin.rewards.*',
            'admin.inventory.*',
            'admin.bom.*',
            'admin.accurate.*',
            'admin.sync.*',
            'admin.settings.daily-auth-code.verify',
        ],
        'DJ' => [
            'admin.dashboard',
            'admin.song-requests.*',
            'admin.display-messages.*',
            'admin.events.*',
            'admin.promos.*',
        ],
        'Kitchen' => [
            'admin.dashboard',
            'admin.kitchen.*',
            'admin.inventory.*',
            'admin.bom.*',
            'admin.menus.*',
            'admin.stock-opname.*',
            'admin.sync.*',
        ],
        'Bar' => [
            'admin.dashboard',
            'admin.bar.*',
            'admin.inventory.*',
            'admin.bom.*',
            'admin.menus.*',
            'admin.sync.*',
        ],
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Collect all unique permission patterns
        $allPermissions = collect(self::ROLE_PERMISSIONS)
            ->flatten()
            ->unique();

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create base roles (including ones not in ROLE_PERMISSIONS)
        foreach (['Administrator', 'Manager', 'Cashier', 'Waiter/Server', 'DJ', 'Kitchen', 'Bar'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // Assign permissions to roles
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findByName($roleName);
            $role->syncPermissions($permissions);
        }

        // Administrator gets all permissions
        Role::findByName('Administrator')->syncPermissions(Permission::all());
    }
}
