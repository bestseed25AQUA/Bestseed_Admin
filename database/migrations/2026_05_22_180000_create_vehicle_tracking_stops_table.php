<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_tracking_stops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->index();
            $table->string('name');                        // "Urapakkam", "Tiruvallur" etc.
            $table->decimal('lat', 10, 6);
            $table->decimal('lng', 10, 6);
            $table->enum('type', ['main', 'sub'])->default('main');  // main stop or sub-stop
            $table->unsignedBigInteger('parent_stop_id')->nullable(); // for sub-stops: which main stop they belong to
            $table->boolean('is_key_stop')->default(false);           // delivery drop points — never disappear
            $table->string('key_type')->nullable();                   // 'drop', 'waypoint', 'destination'
            $table->integer('order')->default(0);                     // display order along route
            $table->decimal('dist_from_pickup_km', 8, 2)->default(0); // distance from pickup in km
            $table->timestamp('passed_at')->nullable();               // when driver passed this stop
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->index(['booking_id', 'order'], 'vts_booking_order_idx');
            $table->index(['booking_id', 'type'], 'vts_booking_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_tracking_stops');
    }
};
