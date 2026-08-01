<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcements')) {
            return;
        }

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('image')->nullable();
            // Which app audience this announcement is aimed at. One role per
            // announcement — the admin form exposes this as a single dropdown.
            $table->enum('audience', ['user', 'driver', 'vendor'])->default('user');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('audience');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
