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
        if (!Schema::hasTable('hatchery_location_branches')) {
            Schema::create('hatchery_location_branches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('location_id');
                $table->string('branch_name');
                $table->string('address')->nullable();
                $table->timestamps();

                $table->foreign('location_id')
                    ->references('id')
                    ->on('hatchery_locations')
                    ->onDelete('cascade');
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hatchery_location_branches');
    }
};
