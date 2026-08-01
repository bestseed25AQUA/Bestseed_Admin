<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add new date columns
        if (!Schema::hasColumn('vehicle_availability', 'start_date')) {
            Schema::table('vehicle_availability', function (Blueprint $table) {
                $table->date('start_date')->nullable()->after('location_ids');
            });
        }
        if (!Schema::hasColumn('vehicle_availability', 'end_date')) {
            Schema::table('vehicle_availability', function (Blueprint $table) {
                $table->date('end_date')->nullable()->after('start_date');
            });
        }
        // Add category_id for dropdown
        if (!Schema::hasColumn('vehicle_availability', 'category_id')) {
            Schema::table('vehicle_availability', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->after('vendor_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_availability', function (Blueprint $table) {
            $columns = ['start_date', 'end_date', 'category_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('vehicle_availability', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
