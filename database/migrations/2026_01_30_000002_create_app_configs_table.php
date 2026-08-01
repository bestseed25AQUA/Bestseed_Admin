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
        if (Schema::hasTable('app_configs')) {
            return;
        }
        Schema::create('app_configs', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique();
            $table->text('config_value')->nullable();
            $table->string('config_group')->nullable()->default('general');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insert default broodstock configuration
        DB::table('app_configs')->insert([
            [
                'config_key' => 'broodstock_default_category',
                'config_value' => 'Tiger',
                'config_group' => 'broodstock',
                'description' => 'Default category filter for broodstock screen',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'config_key' => 'broodstock_default_month',
                'config_value' => date('m'),
                'config_group' => 'broodstock',
                'description' => 'Default month filter for broodstock screen (01-12)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'config_key' => 'broodstock_default_year',
                'config_value' => date('Y'),
                'config_group' => 'broodstock',
                'description' => 'Default year filter for broodstock screen',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_configs');
    }
};
