<?php

namespace App\Models;

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
}
