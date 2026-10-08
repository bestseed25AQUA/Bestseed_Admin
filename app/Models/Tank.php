<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tank extends Model
{
    /**
     * Deleting a tank hides it; it does not destroy it.
     *
     * An admin removing the wrong tank used to take every feed row with it,
     * permanently, and the farm's history simply lost a pond that had existed.
     * Farms were already restorable from the admin panel and tanks were not.
     *
     * Every existing `Tank::where(...)` is filtered by this automatically, so
     * the app stops seeing a deleted tank the moment it goes — which is the
     * behaviour that was already expected. Admin reaches the hidden ones with
     * `withTrashed()` / `onlyTrashed()`.
     */
    use SoftDeletes;

    protected $fillable = [
        'tank_name',
        'farm_id',
        'status',
        'stocking_date',
        'meals',
        'store',
    ];

    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    /** Every crop this tank has carried, newest first. */
    public function batches()
    {
        return $this->hasMany(TankBatch::class, 'tank_id')->orderByDesc('batch_no');
    }

    /**
     * How old the crop in this tank is, in whole days. Day 1 is stocking day.
     *
     * Lives here rather than in a controller because the app and the admin
     * panel both show it and must agree. They did not: the panel worked it out
     * with its own expression and printed "4.9397261758681 days", because
     * Carbon 3 returns a FLOAT from diffInDays and the panel compared a date
     * against `now()` — time of day included — while the app compared two
     * midnights.
     *
     * Both ends are floored to midnight so the answer does not drift with the
     * hour, and the result is cast to int so no caller has to remember to.
     *
     * [$stockingDate] overrides the tank's own, for a tank on its second crop
     * whose age is the BATCH's, not the tank's.
     */
    public function cropDay(?string $stockingDate = null): int
    {
        $date = $stockingDate ?: $this->stocking_date;

        if (!$date) {
            return 0;
        }

        $start = Carbon::parse($date)->startOfDay();
        $today = Carbon::now()->startOfDay();

        // Stocked in the future: not started, rather than a negative count.
        if ($start->greaterThan($today)) {
            return 0;
        }

        return (int) $start->diffInDays($today) + 1;
    }
}
