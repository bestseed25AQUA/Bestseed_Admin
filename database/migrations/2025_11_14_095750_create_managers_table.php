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
        if (!Schema::hasTable('managers')) {
            Schema::create('managers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone')->unique();
                $table->tinyInteger('read_access')->default(0);
                $table->tinyInteger('view_access')->default(0);
                $table->tinyInteger('edit_access')->default(0);
                $table->tinyInteger('delete_access')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('managers');
    }
};
