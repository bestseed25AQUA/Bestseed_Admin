<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            if (!Schema::hasColumn('news', 'subtitle')) {
                $table->string('subtitle')->nullable()->after('title');
            }
            if (!Schema::hasColumn('news', 'call_number')) {
                $table->string('call_number')->nullable()->after('subtitle');
            }
            if (!Schema::hasColumn('news', 'whatsapp_number')) {
                $table->string('whatsapp_number')->nullable()->after('call_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['subtitle', 'call_number', 'whatsapp_number']);
        });
    }
};
