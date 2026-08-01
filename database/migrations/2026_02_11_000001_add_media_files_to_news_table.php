<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            if (!Schema::hasColumn('news', 'media_files')) {
                $table->json('media_files')->nullable()->after('media_path');
            }
            if (!Schema::hasColumn('news', 'media_types')) {
                $table->json('media_types')->nullable()->after('media_files');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['media_files', 'media_types']);
        });
    }
};
