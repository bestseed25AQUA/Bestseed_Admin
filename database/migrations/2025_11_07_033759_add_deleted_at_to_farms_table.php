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
        if (!Schema::hasColumn('farms', 'deleted_at')) {
            Schema::table('farms', function (Blueprint $table) {
                $table->softDeletes(); // adds deleted_at column
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            //
             $table->dropSoftDeletes();
        });
    }
};
