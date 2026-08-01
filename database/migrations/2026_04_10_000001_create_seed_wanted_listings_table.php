<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seed_wanted_listings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('species', ['vannamei', 'tiger'])->default('vannamei');
            $table->enum('count_or_kg', ['count', 'kg'])->default('count');
            $table->string('count_or_kg_value')->nullable(); // e.g. "42c-60c" or "5 kg"
            $table->string('payment')->nullable();
            $table->string('minimum')->nullable();
            $table->string('area')->nullable();
            $table->string('price')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seed_wanted_listings');
    }
};
