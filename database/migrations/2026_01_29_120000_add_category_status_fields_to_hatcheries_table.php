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
        if (!Schema::hasColumn('hatcheries', 'category_available_on')) {
            Schema::table('hatcheries', function (Blueprint $table) {
                $table->date('category_available_on')->nullable()->after('description');
            });
        }

        if (!Schema::hasColumn('hatcheries', 'category_status')) {
            Schema::table('hatcheries', function (Blueprint $table) {
                $table->tinyInteger('category_status')->nullable()->after('category_available_on');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hatcheries', function (Blueprint $table) {
            if (Schema::hasColumn('hatcheries', 'category_available_on')) {
                $table->dropColumn('category_available_on');
            }
            if (Schema::hasColumn('hatcheries', 'category_status')) {
                $table->dropColumn('category_status');
            }
        });
    }
};
