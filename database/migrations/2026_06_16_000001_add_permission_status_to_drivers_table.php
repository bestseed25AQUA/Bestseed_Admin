<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Driver-reported device permission status (set from the driver app).
     *  - location_permission: GPS/location permission granted on the device.
     *  - battery_optimization_disabled: app is exempt from battery optimization
     *    (i.e. background tracking is allowed to keep running).
     *  - permissions_updated_at: last time the driver app synced these.
     */
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            if (!Schema::hasColumn('drivers', 'location_permission')) {
                $table->boolean('location_permission')->default(false)->after('status');
            }
            if (!Schema::hasColumn('drivers', 'battery_optimization_disabled')) {
                $table->boolean('battery_optimization_disabled')->default(false)->after('location_permission');
            }
            if (!Schema::hasColumn('drivers', 'permissions_updated_at')) {
                $table->timestamp('permissions_updated_at')->nullable()->after('battery_optimization_disabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            foreach (['location_permission', 'battery_optimization_disabled', 'permissions_updated_at'] as $col) {
                if (Schema::hasColumn('drivers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
