<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('bookings', 'driver_assigned_at')) {
                $table->timestamp('driver_assigned_at')->nullable()->after('confirmed_at');
            }
            if (!Schema::hasColumn('bookings', 'in_progress_at')) {
                $table->timestamp('in_progress_at')->nullable()->after('driver_assigned_at');
            }
            if (!Schema::hasColumn('bookings', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('in_progress_at');
            }
            if (!Schema::hasColumn('bookings', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'confirmed_at',
                'driver_assigned_at',
                'in_progress_at',
                'delivered_at',
                'cancelled_at',
            ]);
        });
    }
};
