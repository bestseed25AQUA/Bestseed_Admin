<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sessions table — created here only if nothing has already made it.
 *
 * `0001_01_01_000000_create_users_table` also creates `sessions`, the way
 * Laravel's own starter migration does. On the original database the two never
 * collided, because this one ran in a later batch at a time when the first was
 * not yet making the table. Building from scratch today runs both, and the
 * second died on:
 *
 *   Base table or view already exists: 1050 Table 'sessions' already exists
 *
 * Deleting this migration would be the tidier fix, but it is recorded as run
 * on live databases and removing it changes what `migrate:status` reports
 * there. Guarding it costs nothing and leaves both histories true.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        // Deliberately does nothing.
        //
        // This migration can no longer be sure it was the one that created the
        // table, so dropping it on rollback could take out a table belonging to
        // create_users_table — and with it every logged-in session.
    }
};
