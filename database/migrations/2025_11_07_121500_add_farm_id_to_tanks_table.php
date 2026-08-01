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
        if (!Schema::hasColumn('tanks', 'farm_id')) {
            Schema::table('tanks', function (Blueprint $table) {
                // Add farm_id column
                $table->unsignedBigInteger('farm_id')->nullable()->after('id');

                // Foreign key relation with farms table
                $table->foreign('farm_id')->references('id')->on('farms')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tanks', function (Blueprint $table) {
            //
        });
    }
};
