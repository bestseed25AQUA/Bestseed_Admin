<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bookings', 'approaching_notified_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->timestamp('approaching_notified_at')->nullable()->after('last_tracking_alert_at');
            });
        }

        if (!Schema::hasColumn('bookings', 'driver_location_updated_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->timestamp('driver_location_updated_at')->nullable()->after('approaching_notified_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['approaching_notified_at', 'driver_location_updated_at']);
        });
    }
};
