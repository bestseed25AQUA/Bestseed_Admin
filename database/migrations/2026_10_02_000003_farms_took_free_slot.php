<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records that a farm was created against a FREE slot.
 *
 * The free allowance was worked out by counting farms with no subscription,
 * which quietly released the slot the moment that farm moved onto a package:
 *
 *   free plan is 1 farm
 *   farmer creates Pond A          -> free slot used
 *   Pond A's free period lapses
 *   farmer buys a package for it   -> Pond A is now paid...
 *                                  -> ...so nothing is "on free" any more
 *                                  -> the farmer is handed a NEW free farm
 *
 * They could cycle for ever, and worse: the next farm they created took that
 * phantom free slot instead of the package they had just paid for, leaving the
 * package unused.
 *
 * A free slot is spent once and stays spent, so it is recorded on the farm
 * rather than inferred from what is covering it today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->boolean('took_free_slot')->default(false)->after('legacy_free');
            $table->index(['farmer_id', 'took_free_slot']);
        });

        // Every farm that exists now was created under the free plan — no farm
        // had a subscription when per-farm licensing was introduced. Trashed
        // ones included: a restored farm must not come back claiming a slot
        // that was already counted against somebody else.
        DB::table('farms')->update(['took_free_slot' => true]);
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->dropIndex(['farmer_id', 'took_free_slot']);
            $table->dropColumn('took_free_slot');
        });
    }
};
