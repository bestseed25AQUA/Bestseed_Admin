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
        if (!Schema::hasColumn('hatcheries', 'status')) {
            Schema::table('hatcheries', function (Blueprint $table) {
                $table->string('status', 255)->nullable()->after('logo');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hatcheries', function (Blueprint $table) {
            if (Schema::hasColumn('hatcheries', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
