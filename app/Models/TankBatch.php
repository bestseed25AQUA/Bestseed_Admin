<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One crop cycle in one tank: stocked, fed, harvested.
 *
 * A batch opens when a tank is made active and closes when it is made
 * inactive. Everything the app shows for a tank — its running total, its day
 * count, its feed history — belongs to the CURRENT batch, so a second crop
 * starts from zero instead of piling onto the first.
 */
class TankBatch extends Model
{
    protected $fillable = [
        'tank_id',
        'farm_id',
        'batch_no',
        'stocking_date',
        'feed_used_before',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'stocking_date' => 'date',
        'started_at'    => 'datetime',
        'ended_at'      => 'datetime',
    ];

    /** Still running — the tank is stocked and being fed. */
    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('ended_at');
    }

    public function tank()
    {
        return $this->belongsTo(Tank::class, 'tank_id');
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function feeds()
    {
        return $this->hasMany(Feed::class, 'batch_id');
    }

    public function history()
    {
        return $this->hasMany(TankFeedHistory::class, 'batch_id');
    }

    /**
     * The batch a tank is currently on: the open one, or the most recent
     * closed one when the tank is inactive.
     *
     * An inactive tank still has a batch to show — the farmer needs to review
     * and download what they just finished — so this does not return null just
     * because nothing is running.
     */
    public static function currentFor(int $tankId): ?self
    {
        return static::where('tank_id', $tankId)
            ->orderByRaw('ended_at IS NULL DESC')
            ->orderByDesc('id')
            ->first();
    }

    /** The open batch, or null when the tank is inactive. */
    public static function openFor(int $tankId): ?self
    {
        return static::where('tank_id', $tankId)->open()->orderByDesc('id')->first();
    }
}
