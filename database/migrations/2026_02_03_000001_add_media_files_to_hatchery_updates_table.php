<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds media_files and media_types columns for multiple media support
     */
    public function up(): void
    {
        Schema::table('hatchery_updates', function (Blueprint $table) {
            if (!Schema::hasColumn('hatchery_updates', 'media_files')) {
                $table->json('media_files')->nullable()->after('media_path');
            }
            if (!Schema::hasColumn('hatchery_updates', 'media_types')) {
                $table->json('media_types')->nullable()->after('media_files');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hatchery_updates', function (Blueprint $table) {
            $table->dropColumn(['media_files', 'media_types']);
        });
    }
};
