<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanks are soft-deleted now, like farms already were.
 *
 * Deleting a tank used to be permanent, and it took every feed row with it —
 * so an admin removing the wrong tank destroyed weeks of records with no way
 * back, and the farm's history simply lost a pond that had existed. A farm can
 * be restored from the admin panel; there was no reason a tank could not.
 *
 * The feed rows stay put either way now, which is what makes restoring one
 * actually useful rather than handing back an empty tank.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tanks') || Schema::hasColumn('tanks', 'deleted_at')) {
            return;
        }

        Schema::table('tanks', function (Blueprint $table) {
            $table->softDeletes();

            // Every read of a tank now carries `deleted_at IS NULL`, and the
            // hot path is "the tanks on this farm".
            $table->index(['farm_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tanks') || !Schema::hasColumn('tanks', 'deleted_at')) {
            return;
        }

        Schema::table('tanks', function (Blueprint $table) {
            $table->dropIndex(['farm_id', 'deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
