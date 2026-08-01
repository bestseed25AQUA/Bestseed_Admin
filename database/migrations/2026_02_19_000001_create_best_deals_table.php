<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('best_deals')) {
            Schema::create('best_deals', function (Blueprint $table) {
                $table->id();
                $table->string('title', 255);
                $table->string('subtitle', 255)->nullable();
                $table->longText('description')->nullable();
                $table->json('media_files')->nullable();
                $table->json('media_types')->nullable();
                $table->string('media_path')->nullable();
                $table->string('media_type')->nullable();
                $table->string('call_number')->nullable();
                $table->string('whatsapp_number')->nullable();
                $table->boolean('is_active')->default(1);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('best_deals');
    }
};
