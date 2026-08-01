<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_notifications')) {
            return;
        }
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('image')->nullable();
            $table->string('type')->default('admin_broadcast'); // admin_broadcast | booking_status
            $table->json('data')->nullable(); // extra payload (booking_id, status, etc.)
            $table->unsignedBigInteger('farmer_id')->nullable(); // null for broadcast
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('farmer_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};
