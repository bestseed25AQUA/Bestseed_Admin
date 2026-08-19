<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Register the farm management module permissions and grant them to Super Admin.
 *
 * Mirrors the announcements migration: adds only the four new slugs so a live
 * install picks the tab up on deploy without re-syncing every role.
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
                ['slug' => "farm-management.{$slug}"],
                [
                    'name'        => "{$name} Farm Management",
                    'module'      => 'farm-management',
                    'description' => "{$name} access for Farm Management",
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
        Permission::where('module', 'farm-management')->delete();
    }
};
