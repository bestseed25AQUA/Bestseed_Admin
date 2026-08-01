<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('farmers', 'last_active_at')) {
            Schema::table('farmers', function (Blueprint $table) {
                $table->timestamp('last_active_at')->nullable()->after('fcm_token');
            });
        }
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropColumn('last_active_at');
        });
    }
};
