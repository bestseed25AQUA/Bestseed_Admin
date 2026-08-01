<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('hatcheries', 'no_of_pieces')) {
            Schema::table('hatcheries', function (Blueprint $table) {
                $table->integer('no_of_pieces')->nullable()->after('broodstock_count');
            });
        }
    }

    public function down(): void
    {
        Schema::table('hatcheries', function (Blueprint $table) {
            if (Schema::hasColumn('hatcheries', 'no_of_pieces')) {
                $table->dropColumn('no_of_pieces');
            }
        });
    }
};
