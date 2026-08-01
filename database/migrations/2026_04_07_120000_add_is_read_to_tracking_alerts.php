<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tracking_alerts') && !Schema::hasColumn('tracking_alerts', 'is_read')) {
            Schema::table('tracking_alerts', function (Blueprint $table) {
                $table->boolean('is_read')->default(false)->after('location_name');
                $table->index(['vendor_id', 'is_read']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tracking_alerts') && Schema::hasColumn('tracking_alerts', 'is_read')) {
            Schema::table('tracking_alerts', function (Blueprint $table) {
                $table->dropIndex(['vendor_id', 'is_read']);
                $table->dropColumn('is_read');
            });
        }
    }
};

/*
 * ============================================================
 * SQL FOR PRODUCTION (run if you can't run migrations):
 * ============================================================
 *
 * ALTER TABLE `tracking_alerts`
 *   ADD COLUMN `is_read` TINYINT(1) NOT NULL DEFAULT 0 AFTER `location_name`;
 *
 * CREATE INDEX `tracking_alerts_vendor_id_is_read_index`
 *   ON `tracking_alerts` (`vendor_id`, `is_read`);
 *
 * ============================================================
 */
