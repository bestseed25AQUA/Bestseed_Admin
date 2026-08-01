<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist a road-snapped polyline per booking so the customer/employee
     * apps' green "travelled" line can be served straight from the DB —
     * no Roads API call on read. Snap is run incrementally inside
     * `addTrackingPoint` (a few new GPS samples at a time, with overlap
     * context against the tail of the existing path), drastically cutting
     * Google Maps API spend at scale.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'tracking_path')) {
                $table->json('tracking_path')->nullable()->after('driver_location_updated_at');
            }
            if (!Schema::hasColumn('bookings', 'tracking_path_last_id')) {
                // Largest vehicle_trackings.id we've already folded into tracking_path.
                // Lets the writer pick up exactly where it left off.
                $table->unsignedBigInteger('tracking_path_last_id')->nullable()->after('tracking_path');
            }
            if (!Schema::hasColumn('bookings', 'tracking_path_at')) {
                $table->timestamp('tracking_path_at')->nullable()->after('tracking_path_last_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            foreach (['tracking_path', 'tracking_path_last_id', 'tracking_path_at'] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
