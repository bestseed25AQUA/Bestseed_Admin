<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency guard for spot-hatchery stock.
 *
 * When a booking is confirmed we subtract its no_of_pieces from the spot
 * hatchery's available pieces, and when it is cancelled we credit them back.
 * Both events can fire more than once (a booking can be re-saved, re-confirmed
 * after a cancellation, or cancelled from admin / vendor / customer app), so a
 * marker is needed to know whether THIS booking currently holds stock.
 *
 * Non-null = the pieces are currently deducted from the hatchery.
 * Null      = they are not (never confirmed, or already credited back).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bookings', 'stock_applied_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->timestamp('stock_applied_at')->nullable()->after('cancelled_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'stock_applied_at')) {
                $table->dropColumn('stock_applied_at');
            }
        });
    }
};
