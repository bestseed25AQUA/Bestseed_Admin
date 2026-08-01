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
        if (!Schema::hasTable('vehicle_gallery')) {
            Schema::create('vehicle_gallery', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vehicle_availability_id');
                $table->string('file_path');
                $table->enum('file_type', ['image', 'video'])->default('image');
                $table->string('original_name')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('vehicle_availability_id')
                    ->references('id')
                    ->on('vehicle_availability')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_gallery');
    }
};
