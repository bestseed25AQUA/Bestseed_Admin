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
        if (!Schema::hasTable('vehicle_availability')) {
            Schema::create('vehicle_availability', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('hatchery_id');
                $table->unsignedBigInteger('vendor_id');
                $table->json('location_ids');
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->date('available_on')->nullable();
                $table->integer('available_space')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('hatchery_id')->references('id')->on('hatcheries')->onDelete('cascade');
                $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_availability');
    }
};
