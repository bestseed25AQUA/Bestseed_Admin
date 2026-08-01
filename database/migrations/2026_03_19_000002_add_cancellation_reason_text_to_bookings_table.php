<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bookings', 'cancellation_reason_text')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('cancellation_reason_text')->nullable()->after('cancellation_reason');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('cancellation_reason_text');
        });
    }
};
