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
        if (!Schema::hasColumn('managers', 'is_partner')) {
            Schema::table('managers', function (Blueprint $table) {
                $table->tinyInteger('is_partner')
                      ->default(0)
                      ->comment('0 = Manager, 1 = Partner')
                      ->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('managers', function (Blueprint $table) {
            //
        });
    }
};
