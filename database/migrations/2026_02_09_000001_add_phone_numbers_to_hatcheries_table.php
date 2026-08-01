<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hatcheries', function (Blueprint $table) {
            if (!Schema::hasColumn('hatcheries', 'call_number')) {
                $table->string('call_number', 15)->nullable()->after('description');
            }
            if (!Schema::hasColumn('hatcheries', 'whatsapp_number')) {
                $table->string('whatsapp_number', 15)->nullable()->after('call_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hatcheries', function (Blueprint $table) {
            $table->dropColumn(['call_number', 'whatsapp_number']);
        });
    }
};
