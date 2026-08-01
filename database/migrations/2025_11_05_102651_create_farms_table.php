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
        if (!Schema::hasTable('farms')) {
            Schema::create('farms', function (Blueprint $table) {
                $table->id();
                $table->string('farm_name');
                $table->unsignedBigInteger('farmer_id');
                $table->date('stocking_date')->nullable();
                $table->integer('no_of_tanks')->default(0);
                $table->string('store')->nullable();
                $table->integer('low_feed_limit')->nullable();
                $table->timestamps();

                // If farmer_id references another table (like 'farmers')
                $table->foreign('farmer_id')->references('id')->on('farmers')->onDelete('cascade');
            });
        }
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
