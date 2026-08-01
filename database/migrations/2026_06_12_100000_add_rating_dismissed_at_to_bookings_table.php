<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookings') && !Schema::hasColumn('bookings', 'rating_dismissed_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                // Set when the farmer closes/cancels the rating popup so we
                // never prompt for this booking again (across devices/reinstalls).
                $table->timestamp('rating_dismissed_at')->nullable()->after('delivered_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'rating_dismissed_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('rating_dismissed_at');
            });
        }
    }
};
