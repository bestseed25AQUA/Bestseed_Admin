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
        if (!Schema::hasTable('spot_hatcheries')) {
            Schema::create('spot_hatcheries', function (Blueprint $table) {

                 $table->id();
                $table->unsignedBigInteger('brand_id')->nullable();
                $table->string('hatchery_name', 150);
                $table->string('location', 150)->nullable();
                $table->unsignedBigInteger('location_id')->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->enum('status', ['open', 'closed', 'coming_soon', 'spot'])->default('open');
                $table->date('available_on')->nullable();
                $table->longText('categories')->nullable();
                $table->time('opening_time')->nullable();
                $table->time('closing_time')->nullable();
                $table->text('image')->nullable();
                $table->string('brand', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spot_hatcheries');
    }
};
