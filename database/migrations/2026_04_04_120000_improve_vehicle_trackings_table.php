<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Improve vehicle_trackings for distance-based insertion & time-ordered queries.
     */
    public function up(): void
    {
        // Check if index already exists (safe for servers where it was added manually)
        $indexExists = collect(DB::select("SHOW INDEX FROM vehicle_trackings WHERE Key_name = 'vt_booking_reached_idx'"))->isNotEmpty();

        if (!$indexExists) {
            Schema::table('vehicle_trackings', function (Blueprint $table) {
                $table->index(['booking_id', 'reached_at'], 'vt_booking_reached_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexExists = collect(DB::select("SHOW INDEX FROM vehicle_trackings WHERE Key_name = 'vt_booking_reached_idx'"))->isNotEmpty();

        if ($indexExists) {
            Schema::table('vehicle_trackings', function (Blueprint $table) {
                $table->dropIndex('vt_booking_reached_idx');
            });
        }
    }
};
