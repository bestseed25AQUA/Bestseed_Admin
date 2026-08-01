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
        if (!Schema::hasTable('tanks')) {
            Schema::create('tanks', function (Blueprint $table) {
                $table->id();
                $table->string('tank_name');
                $table->tinyInteger('status')->default(1)->comment('0 = inactive, 1 = active');
                $table->unsignedInteger('store')->default(0)->comment('store in kg (or unit as you prefer)');
                $table->decimal('total_feed_used', 10, 2)->default(0.00)->comment('total feed used in kg');
                $table->unsignedInteger('meals')->default(0)->comment('number of meals given');
                $table->decimal('feed_quantity', 10, 2)->default(0.00)->comment('feed quantity per meal (kg)');
                $table->date('stocking_date')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tanks');
    }
};
