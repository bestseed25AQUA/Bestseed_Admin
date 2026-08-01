<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Define modules and their permissions
        $modules = [
            'vendors' => 'Vendors Management',
            'hatcheries' => 'Hatcheries Management',
            'hatchery-categories' => 'Hatchery Categories',
            'hatchery-locations' => 'Hatchery Locations',
            'hatchery-seeds' => 'Hatchery Seeds',
            'broad-stocks' => 'Broad Stocks',
            'spot-hatcheries' => 'Spot Hatcheries',
            'bookings' => 'Bookings Management',
            'drivers' => 'Drivers Management',
            'vehicle-availability' => 'Vehicle Availability',
            'banners' => 'Banners Management',
            'market-prices' => 'Market Prices',
            'news' => 'News Management',
            'announcements' => 'Announcements Management',
            'users' => 'Users Management',
            'settings' => 'Settings Management',
            'roles' => 'Roles Management',
            'sub-admins' => 'Sub Admins Management',
        ];

        $actions = [
            'view' => 'View',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
        ];

        // Create permissions for each module
        $allPermissions = [];
        foreach ($modules as $moduleSlug => $moduleName) {
            foreach ($actions as $actionSlug => $actionName) {
                $permission = Permission::firstOrCreate(
                    ['slug' => "{$moduleSlug}.{$actionSlug}"],
                    [
                        'name' => "{$actionName} {$moduleName}",
                        'module' => $moduleSlug,
                        'description' => "{$actionName} access for {$moduleName}",
                    ]
                );
                $allPermissions[] = $permission->id;
            }
        }

        // Create Super Admin Role (all permissions)
        $superAdminRole = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full access to all system features',
                'is_default' => false,
            ]
        );
        $superAdminRole->permissions()->sync($allPermissions);

        // Create Manager Role (view + update permissions)
        $managerRole = Role::firstOrCreate(
            ['slug' => 'manager'],
            [
                'name' => 'Manager',
                'description' => 'Can view and update most features',
                'is_default' => false,
            ]
        );
        $managerPermissions = Permission::whereIn('slug', [
            'vendors.view', 'vendors.update',
            'hatcheries.view', 'hatcheries.update',
            'hatchery-categories.view', 'hatchery-categories.update',
            'hatchery-locations.view', 'hatchery-locations.update',
            'hatchery-seeds.view', 'hatchery-seeds.update',
            'broad-stocks.view', 'broad-stocks.update',
            'spot-hatcheries.view', 'spot-hatcheries.update',
            'bookings.view', 'bookings.update',
            'drivers.view', 'drivers.update',
            'vehicle-availability.view', 'vehicle-availability.update',
            'banners.view', 'banners.update',
            'market-prices.view', 'market-prices.update',
            'news.view', 'news.update',
            'announcements.view', 'announcements.update',
            'users.view', 'users.update',
        ])->pluck('id')->toArray();
        $managerRole->permissions()->sync($managerPermissions);

        // Create Viewer Role (view only permissions)
        $viewerRole = Role::firstOrCreate(
            ['slug' => 'viewer'],
            [
                'name' => 'Viewer',
                'description' => 'Can only view data, no modifications allowed',
                'is_default' => true,
            ]
        );
        $viewerPermissions = Permission::where('slug', 'like', '%.view')
            ->whereNotIn('module', ['roles', 'sub-admins', 'settings'])
            ->pluck('id')
            ->toArray();
        $viewerRole->permissions()->sync($viewerPermissions);

        // Create Booking Manager Role
        $bookingManagerRole = Role::firstOrCreate(
            ['slug' => 'booking-manager'],
            [
                'name' => 'Booking Manager',
                'description' => 'Full access to bookings, drivers, and vehicles',
                'is_default' => false,
            ]
        );
        $bookingPermissions = Permission::whereIn('module', [
            'bookings', 'drivers', 'vehicle-availability'
        ])->pluck('id')->toArray();
        $bookingManagerRole->permissions()->sync($bookingPermissions);

        // Create Content Manager Role
        $contentManagerRole = Role::firstOrCreate(
            ['slug' => 'content-manager'],
            [
                'name' => 'Content Manager',
                'description' => 'Manage banners, news, and market prices',
                'is_default' => false,
            ]
        );
        $contentPermissions = Permission::whereIn('module', [
            'banners', 'news', 'market-prices'
        ])->pluck('id')->toArray();
        $contentManagerRole->permissions()->sync($contentPermissions);
    }
}
