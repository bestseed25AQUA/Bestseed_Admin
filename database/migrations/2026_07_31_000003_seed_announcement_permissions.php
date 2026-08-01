<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Register the announcements module permissions and grant them to Super Admin.
 *
 * RolesAndPermissionsSeeder also lists the module, but re-running the whole
 * seeder on a live install would re-sync every role. This migration adds just
 * the four new slugs so existing deployments pick the tab up on deploy.
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
                ['slug' => "announcements.{$slug}"],
                [
                    'name'        => "{$name} Announcements Management",
                    'module'      => 'announcements',
                    'description' => "{$name} access for Announcements Management",
                ]
            );
            $ids[] = $permission->id;
        }

        // Super Admin gets every permission by definition — attach without
        // disturbing anything already granted.
        $superAdmin = Role::where('slug', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function down(): void
    {
        Permission::where('module', 'announcements')->delete();
    }
};
