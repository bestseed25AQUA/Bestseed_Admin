<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Active/Inactive flag for a hatchery. When Inactive the hatchery and its
     * data are hidden from customers (the user app); admins/vendors still see
     * it so it can be reactivated. Defaults to Active for all existing rows.
     */
    public function up(): void
    {
        Schema::table('hatcheries', function (Blueprint $table) {
            if (!Schema::hasColumn('hatcheries', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('hatchery_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hatcheries', function (Blueprint $table) {
            if (Schema::hasColumn('hatcheries', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
