<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The harvest COUNT — pieces per kilogram.
 *
 * The standard trade measure for a shrimp crop, and the one that sets the
 * price: "count 30" means thirty shrimp to the kilo, which are large and
 * valuable, while count 80 are small and fetch much less. A harvest reported
 * as a weight alone is only half the result — a farmer always says
 * "2,500 kg at count 50".
 *
 * Nullable, exactly like [harvest_quantity] beside it. The count usually comes
 * back from the buyer, sometimes days after the tank was emptied, so a farmer
 * closing a crop today may genuinely not know it yet. Null means "not
 * recorded", which is not the same as a count of zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tank_batches')) {
            return;
        }

        Schema::table('tank_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('tank_batches', 'harvest_count')) {
                // Unsigned: a count below one is not a thing. An integer
                // because the trade quotes whole numbers — 30, 50, 80 — and
                // storing 49.7 would invite a precision nobody uses.
                $table->unsignedInteger('harvest_count')
                    ->nullable()
                    ->after('harvest_quantity');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tank_batches')) {
            return;
        }

        Schema::table('tank_batches', function (Blueprint $table) {
            if (Schema::hasColumn('tank_batches', 'harvest_count')) {
                $table->dropColumn('harvest_count');
            }
        });
    }
};
