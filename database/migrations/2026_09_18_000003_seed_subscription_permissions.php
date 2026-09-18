<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Permissions for the Subscriptions screen, granted to super-admin.
 *
 * Same shape as the farm-management seeder so the sidebar's @permission checks
 * and the controller's middleware line up with every other module.
 */
return new class extends Migration
{
    public function up(): void
    {
        $actions = [
            'view'   => 'View',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
        ];

        $ids = [];

        foreach ($actions as $slug => $name) {
            $permission = Permission::firstOrCreate(
                ['slug' => "subscriptions.{$slug}"],
                [
                    'name'        => "{$name} Subscriptions",
                    'module'      => 'subscriptions',
                    'description' => "{$name} access for Farm Management Subscriptions",
                ]
            );

            $ids[] = $permission->id;
        }

        $superAdmin = Role::where('slug', 'super-admin')->first();

        if ($superAdmin) {
            $superAdmin->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function down(): void
    {
        Permission::where('module', 'subscriptions')->delete();
    }
};
