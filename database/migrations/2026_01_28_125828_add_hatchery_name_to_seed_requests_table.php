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
        if (!Schema::hasColumn('seed_requests', 'hatchery_name')) {
            Schema::table('seed_requests', function (Blueprint $table) {
                $table->string('hatchery_name')->nullable()->after('hatchery_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seed_requests', function (Blueprint $table) {
            $table->dropColumn('hatchery_name');
        });
    }
};
