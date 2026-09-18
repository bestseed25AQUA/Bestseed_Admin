<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per reminder actually sent, so none is ever sent twice.
 *
 * The expiry command runs daily and asks "is this subscription 7 days out?".
 * Without a record of what went before, a cron that runs twice, a manual
 * re-run, or a server whose clock slips would send the same warning again. The
 * unique key makes a duplicate a database error rather than a second push at
 * six in the morning.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_reminders')) {
            return;
        }

        Schema::create('subscription_reminders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('subscription_id');

            // Which threshold this was: 7 means "7 days left". 0 is the
            // notice sent on the day it expires.
            $table->unsignedSmallInteger('days_before');

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(['subscription_id', 'days_before'], 'subscription_reminder_unique');
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_reminders');
    }
};
