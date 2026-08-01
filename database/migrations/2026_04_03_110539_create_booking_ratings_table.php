<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('booking_ratings')) {
            Schema::create('booking_ratings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
                $table->foreignId('farmer_id')->constrained('farmers')->onDelete('cascade');
                $table->unsignedTinyInteger('rating'); // 1-5 stars
                $table->text('message')->nullable();
                $table->timestamps();

                $table->unique('booking_id'); // one rating per booking
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_ratings');
    }
};
