<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bookings', 'driver_inactive_reason')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('driver_inactive_reason')->nullable()->after('driver_location_updated_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('driver_inactive_reason');
        });
    }
};
