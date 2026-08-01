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
        if (Schema::hasColumn('managers', 'read_access') && !Schema::hasColumn('managers', 'create_access')) {
            Schema::table('managers', function (Blueprint $table) {
                $table->renameColumn('read_access', 'create_access');
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
            $table->renameColumn('create_access', 'read_access');
        });
    }
};
