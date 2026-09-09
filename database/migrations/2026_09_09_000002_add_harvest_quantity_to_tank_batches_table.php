<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the crop weighed when it came out.
     *
     * Recorded on the BATCH, not the tank: a tank is stocked, fed, harvested
     * and stocked again, so the figure belongs to the crop being closed. Kept
     * per batch, FCR is simply that batch's feed divided by this.
     *
     * Nullable, because it is optional — a farmer may harvest without weighing,
     * and 0 would read as "harvested nothing" rather than "not recorded".
     */
    public function up(): void
    {
        Schema::table('tank_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('tank_batches', 'harvest_quantity')) {
                $table->decimal('harvest_quantity', 12, 2)->nullable()->after('feed_used_before');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tank_batches', function (Blueprint $table) {
            if (Schema::hasColumn('tank_batches', 'harvest_quantity')) {
                $table->dropColumn('harvest_quantity');
            }
        });
    }
};
