<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separates "grandfathered" from "never covered".
 *
 * [2026_10_02_000001] used `free_until = NULL` to mean "never expires", which
 * protected the farms that already existed. But NULL is also what a brand new
 * farm gets when nothing could cover it — an admin ticking "Create anyway" past
 * a farmer's allowance — and those two must behave in opposite ways. The second
 * read as a free farm that never expires, so a farm created deliberately over
 * the allowance stayed fully open.
 *
 * The grandfathering is now said out loud instead of being inferred from a NULL:
 *
 *   legacy_free = 1  created under the old unlimited free plan; never locks
 *   legacy_free = 0  licensed normally; a package or a free period, or locked
 *
 * Set for every farm that exists as this runs, and 0 for everything after. The
 * flag is deliberately a column an admin can clear later, when those farms are
 * to be brought onto the paid plan — that is a commercial decision, not one for
 * a migration to make on everybody's behalf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->boolean('legacy_free')->default(false)->after('free_until');
        });

        // Every farm that exists right now, trashed ones included: a farm that
        // is restored tomorrow must come back in the state it left in rather
        // than locked.
        DB::table('farms')
            ->whereNull('covered_by_subscription_id')
            ->update(['legacy_free' => true]);
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->dropColumn('legacy_free');
        });
    }
};
