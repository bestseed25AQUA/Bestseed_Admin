<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give a farm an active/inactive switch.
 *
 * Deleting was previously the only way to take a farm out of circulation, which
 * is destructive and irreversible for the owner. Status lets an admin park a
 * farm — it disappears from the app for the owner and every manager/partner —
 * while the record, its tanks and its feed history stay untouched.
 *
 * Existing farms are active, so nothing changes on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            if (!Schema::hasColumn('farms', 'status')) {
                $table->tinyInteger('status')->default(1)->after('farmer_id');
                $table->index('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            if (Schema::hasColumn('farms', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }
        });
    }
};
