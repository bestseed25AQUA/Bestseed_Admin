<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a farm's free period ended, kept for the record.
 *
 * `free_until` is a LICENSING column: FarmLicenceService::attach clears it the
 * moment a package takes over, deliberately, so a lapsed package can never
 * fall back through it and quietly re-open the farm. The side effect is that
 * the date disappears, and a farm's history then shows a free trial with no
 * end — the free period is known to have happened, but not when it finished.
 *
 * This column is written once and never read by the licensing rules, so it can
 * survive the handover and answer "what was this farm on before?".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('farms', 'free_ended_on')) {
            return;
        }

        Schema::table('farms', function (Blueprint $table) {
            $table->date('free_ended_on')->nullable()->after('free_until');
        });

        // Farms still inside their free period, or lapsed but never sold to,
        // still carry the date — copy it across. Farms already handed over to
        // a package lost it before this column existed and stay null; the page
        // says so rather than inventing one.
        DB::table('farms')
            ->whereNotNull('free_until')
            ->where('took_free_slot', true)
            ->update(['free_ended_on' => DB::raw('free_until')]);
    }

    public function down(): void
    {
        if (!Schema::hasColumn('farms', 'free_ended_on')) {
            return;
        }

        Schema::table('farms', function (Blueprint $table) {
            $table->dropColumn('free_ended_on');
        });
    }
};
