<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One free-text note per tank per day.
 *
 * A table of its own rather than a column on `tank_feed_histories`, because a
 * note has to be possible on a day with NO feed recorded — a farmer noting why
 * a tank was not fed ("water change", "aerator down") is exactly the day that
 * carries no feed row to hang the note on. The history screen already draws a
 * card for every day since stocking, fed or not, and every one of them can take
 * a note.
 *
 * `batch_id` is recorded for context but NOT part of the key: a tank cannot
 * have the same calendar day twice, so (tank_id, note_date) is already unique,
 * and keying on the batch would let a re-stocked tank hold two notes for one
 * date with nothing to say which the screen should show.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tank_day_notes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tank_id');
            $table->unsignedBigInteger('farm_id');

            // Which crop cycle the day fell in, for reports and admin. Nullable
            // because a tank whose rows predate batches has none.
            $table->unsignedBigInteger('batch_id')->nullable();

            $table->date('note_date');
            $table->text('note');

            // Who wrote it. A farm can have several people recording.
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            // One note per day per tank — saving again rewrites it rather than
            // stacking a second note the screen would have to choose between.
            $table->unique(['tank_id', 'note_date']);

            // The history screen asks for one tank's notes across a date range.
            $table->index(['tank_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tank_day_notes');
    }
};
