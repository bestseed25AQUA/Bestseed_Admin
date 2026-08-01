<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Farmer's preferred delivery date, captured on the booking form alongside
     * the packing date. Kept separate from `delivery_datetime` (which the
     * vendor/employee sets later as the actual/expected delivery time).
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'delivery_date')) {
                $table->date('delivery_date')->nullable()->after('packing_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'delivery_date')) {
                $table->dropColumn('delivery_date');
            }
        });
    }
};
