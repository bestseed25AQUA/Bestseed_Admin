<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The name ONE farm owner calls ONE member by.
 *
 * Deliberately on the membership row, not on `farmers`. A farmer's own name is
 * theirs — it is what they registered with and what every other farm they hold
 * access to sees — so an owner typing "Ramesh (pump)" here must not rewrite it
 * for everybody. Two farms can label the same person differently, and neither
 * touches the person's profile.
 *
 * Nullable: blank means "no label", and the farmer's own name is shown instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_access_members', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('farm_access_members', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
