<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two abilities the app grants that this table could not hold.
     *
     * "Tank Active / Inactive" and "Total Feed" have been on the admin form and
     * in FarmAccessMember for a while, but `managers` only had
     * view/edit/create/delete — so ticking either saved nothing and the row came
     * back unticked, with no error to explain it.
     *
     * Default 0: nobody silently gains an ability on an existing row. The app's
     * defaults for a NEW member are applied by the form, not by the column.
     */
    public function up(): void
    {
        Schema::table('managers', function (Blueprint $table) {
            if (!Schema::hasColumn('managers', 'tank_status_access')) {
                $table->tinyInteger('tank_status_access')->default(0)->after('edit_access');
            }

            if (!Schema::hasColumn('managers', 'total_feed_access')) {
                $table->tinyInteger('total_feed_access')->default(0)->after('tank_status_access');
            }
        });
    }

    public function down(): void
    {
        Schema::table('managers', function (Blueprint $table) {
            foreach (['tank_status_access', 'total_feed_access'] as $col) {
                if (Schema::hasColumn('managers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
