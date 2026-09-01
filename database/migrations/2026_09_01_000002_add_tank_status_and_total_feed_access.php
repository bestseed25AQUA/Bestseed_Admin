<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two abilities split out of the blanket "edit" permission.
 *
 *   tank_status_access — mark a tank active or inactive (harvest it)
 *   total_feed_access  — change the farm's feed store and low-feed limit
 *
 * Both used to ride on "edit", which meant anyone who could correct a feed
 * entry could also harvest a tank or rewrite the farm's stock figure. They are
 * different kinds of decision and now carry their own permission.
 *
 * Existing members keep what they effectively had: whatever "edit" gave them
 * for tank status, and — deliberately — no access to the feed store, since
 * that is the new default and the riskier of the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_access_members', function (Blueprint $table) {
            $table->boolean('tank_status_access')->default(1)->after('edit_access');
            $table->boolean('total_feed_access')->default(0)->after('tank_status_access');
        });

        // Nobody loses an ability they were already using.
        DB::table('farm_access_members')->update([
            'tank_status_access' => DB::raw('edit_access'),
        ]);
    }

    public function down(): void
    {
        Schema::table('farm_access_members', function (Blueprint $table) {
            $table->dropColumn(['tank_status_access', 'total_feed_access']);
        });
    }
};
