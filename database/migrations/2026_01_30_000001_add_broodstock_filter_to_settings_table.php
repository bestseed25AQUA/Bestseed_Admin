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
        Schema::table('settings', function (Blueprint $table) {
            $table->string('broodstock_default_category')->nullable()->default('Tiger');
            $table->string('broodstock_default_month')->nullable();
            $table->string('broodstock_default_year')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['broodstock_default_category', 'broodstock_default_month', 'broodstock_default_year']);
        });
    }
};
