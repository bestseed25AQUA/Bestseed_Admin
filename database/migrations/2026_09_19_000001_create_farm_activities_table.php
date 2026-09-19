<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who changed what on a farm, and when.
 *
 * A farm is worked by several people at once — the owner, partners, managers,
 * and an admin on the phone to any of them. When a figure looks wrong there
 * was previously no way to ask who put it there, so every disagreement ended
 * as somebody's word against somebody else's.
 *
 * Deliberately denormalised. Names, mobiles and tank names are COPIED in at
 * the moment of writing rather than joined at read time, because the history
 * has to stay readable after a tank is deleted, a manager's access is revoked,
 * or someone changes their name. A log that rewrites itself is not a log.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('farm_activities')) {
            return;
        }

        Schema::create('farm_activities', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('farm_id');

            // The tank this was about, when it was about one. Not a foreign
            // key: tanks are hard-deleted, and the entry recording that
            // deletion must outlive them.
            $table->unsignedBigInteger('tank_id')->nullable();
            $table->string('tank_name', 100)->nullable();

            // What area of Farm Management this belongs to: farm, tank, feed,
            // store, access. Drives the filter chips on both screens.
            $table->string('category', 30);

            // created | updated | deleted | activated | harvested | granted |
            // revoked. Drives the colour and the icon.
            $table->string('action', 30);

            // One plain sentence, written when the change happened — the only
            // moment both the old and the new value are known.
            $table->string('description', 500);

            // Field-level before/after, for the admin panel's detail view.
            // Never shown raw to a farmer.
            $table->json('changes')->nullable();

            // farmer | admin. Two different tables, so the type is stored
            // alongside the id rather than a polymorphic relation nobody
            // would eager-load anyway.
            $table->string('actor_type', 20)->default('farmer');
            $table->unsignedBigInteger('actor_id')->nullable();

            // Snapshots, for the reason in the class docblock above.
            $table->string('actor_name', 150)->nullable();
            $table->string('actor_mobile', 20)->nullable();

            // The actor's standing on THIS farm when they acted: owner,
            // partner, manager or admin. A person's role changes; what they
            // were when they did it does not.
            $table->string('actor_role', 20)->nullable();

            $table->timestamps();

            // Both screens read one farm's entries newest-first inside a date
            // window, which is exactly this index.
            $table->index(['farm_id', 'created_at']);
            $table->index(['farm_id', 'category']);
            $table->index('tank_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_activities');
    }
};
