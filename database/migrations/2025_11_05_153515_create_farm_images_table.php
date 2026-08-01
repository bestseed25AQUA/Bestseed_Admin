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
        if (!Schema::hasTable('farm_images')) {
            Schema::create('farm_images', function (Blueprint $table) {
                $table->id();

                // Foreign key linking to farms table
                $table->unsignedBigInteger('farm_id');

                // Store multiple image paths as JSON
                $table->json('images')->nullable();

                $table->timestamps();

                // Optional: relation to farms table
                $table->foreign('farm_id')
                      ->references('id')
                      ->on('farms')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_images');
    }
};
