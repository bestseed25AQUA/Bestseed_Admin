<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let the "feed already used" figure be edited without destroying real history.
 *
 * Registering a farm that was stocked weeks ago generates one feed row per tank
 * per day from a single total. If the farmer later corrects that total we have
 * to remove the old rows and write new ones — but a farm may also have feed the
 * farmer entered by hand since, and that must survive untouched.
 *
 * `is_backfill` marks the generated rows so only they are replaced.
 * `farms.feed_used_before` remembers the figure that produced them, so the edit
 * form can show what was actually entered rather than inferring it from a sum.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['feeds', (new \App\Models\TankFeedHistory)->getTable()] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'is_backfill')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->tinyInteger('is_backfill')->default(0)->after('feed_date');
                    $t->index('is_backfill');
                });
            }
        }

        if (!Schema::hasColumn('farms', 'feed_used_before')) {
            Schema::table('farms', function (Blueprint $t) {
                $t->decimal('feed_used_before', 12, 2)->nullable()->after('low_feed_limit');
            });
        }
    }

    public function down(): void
    {
        foreach (['feeds', (new \App\Models\TankFeedHistory)->getTable()] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'is_backfill')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex(['is_backfill']);
                    $t->dropColumn('is_backfill');
                });
            }
        }

        if (Schema::hasColumn('farms', 'feed_used_before')) {
            Schema::table('farms', function (Blueprint $t) {
                $t->dropColumn('feed_used_before');
            });
        }
    }
};
