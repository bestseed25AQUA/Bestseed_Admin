<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add fcm_token to vendors table for push notifications
        if (!Schema::hasColumn('vendors', 'fcm_token')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('fcm_token', 500)->nullable()->after('status');
            });
        }

        // Add fcm_token to drivers table for push notifications
        if (!Schema::hasColumn('drivers', 'fcm_token')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->string('fcm_token', 500)->nullable()->after('status');
            });
        }

        // Add last_tracking_alert_at to bookings table to rate-limit alerts
        if (!Schema::hasColumn('bookings', 'last_tracking_alert_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->timestamp('last_tracking_alert_at')->nullable()->after('cancelled_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('last_tracking_alert_at');
        });
    }
};

/*
 * ============================================================
 * SQL COMMANDS TO RUN DIRECTLY ON PRODUCTION DATABASE:
 * ============================================================
 *
 * ALTER TABLE `vendors` ADD COLUMN `fcm_token` VARCHAR(500) NULL AFTER `status`;
 *
 * ALTER TABLE `drivers` ADD COLUMN `fcm_token` VARCHAR(500) NULL AFTER `status`;
 *
 * ALTER TABLE `bookings` ADD COLUMN `last_tracking_alert_at` TIMESTAMP NULL AFTER `cancelled_at`;
 *
 * ============================================================
 */
