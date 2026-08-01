<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcement_reads')) {
            return;
        }

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            // The recipient is a farmer, driver or vendor row — ids overlap
            // between those tables, so the audience is part of the identity.
            $table->enum('audience', ['user', 'driver', 'vendor']);
            $table->unsignedBigInteger('recipient_id');
            // Set once the announcement has been offered to this recipient as a
            // popup, so it never pops a second time. Separate from read_at: when
            // several are unread only the newest actually pops, the rest are
            // marked shown but stay unread in the in-app list.
            $table->timestamp('popup_shown_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'audience', 'recipient_id'], 'announcement_reads_unique');
            $table->index(['audience', 'recipient_id']);

            $table->foreign('announcement_id')->references('id')->on('announcements')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
    }
};
