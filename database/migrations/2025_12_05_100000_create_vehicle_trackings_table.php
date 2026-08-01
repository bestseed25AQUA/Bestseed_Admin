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
        if (!Schema::hasTable('vehicle_trackings')) {
            Schema::create('vehicle_trackings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_id');
                $table->string('location_name')->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->enum('status', ['completed', 'current', 'pending'])->default('pending');
                $table->string('title')->nullable();
                $table->string('subtitle')->nullable();
                $table->timestamp('reached_at')->nullable();
                $table->integer('order')->default(0);
                $table->timestamps();

                $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            });
        }

        // Add driver location fields to bookings table
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'driver_lat')) {
                $table->decimal('driver_lat', 10, 7)->nullable()->after('driver_mobile');
            }
            if (!Schema::hasColumn('bookings', 'driver_lng')) {
                $table->decimal('driver_lng', 10, 7)->nullable()->after('driver_lat');
            }
            if (!Schema::hasColumn('bookings', 'driver_location_name')) {
                $table->string('driver_location_name')->nullable()->after('driver_lng');
            }
            if (!Schema::hasColumn('bookings', 'driver_image')) {
                $table->string('driver_image')->nullable()->after('driver_location_name');
            }
            if (!Schema::hasColumn('bookings', 'pickup_lat')) {
                $table->decimal('pickup_lat', 10, 7)->nullable()->after('driver_image');
            }
            if (!Schema::hasColumn('bookings', 'pickup_lng')) {
                $table->decimal('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
            }
            if (!Schema::hasColumn('bookings', 'drop_lat')) {
                $table->decimal('drop_lat', 10, 7)->nullable()->after('pickup_lng');
            }
            if (!Schema::hasColumn('bookings', 'drop_lng')) {
                $table->decimal('drop_lng', 10, 7)->nullable()->after('drop_lat');
            }
            if (!Schema::hasColumn('bookings', 'delivery_expected')) {
                $table->date('delivery_expected')->nullable()->after('drop_lng');
            }
            if (!Schema::hasColumn('bookings', 'delivery_note')) {
                $table->text('delivery_note')->nullable()->after('delivery_expected');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_trackings');

        Schema::table('bookings', function (Blueprint $table) {
            $columns = [
                'driver_lat',
                'driver_lng',
                'driver_location_name',
                'driver_image',
                'pickup_lat',
                'pickup_lng',
                'drop_lat',
                'drop_lng',
                'delivery_expected',
                'delivery_note',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
