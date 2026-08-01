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
        if (!Schema::hasTable('tank_feed_histories')) {
            Schema::create('tank_feed_histories', function (Blueprint $table) {
               $table->id();
                $table->string('meals');
                $table->unsignedBigInteger('tank_id');
                $table->unsignedBigInteger('farm_id');

                $table->decimal('feed_quantity', 10, 2)->default(0.00);

                $table->date('feed_date')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tank_feed_histories');
    }
};
