<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What someone wrote about one tank on one day.
 *
 * Free text, deliberately: it holds the things the numbers cannot — why a tank
 * was not fed, that the water was changed, that the aerator failed. See the
 * migration for why this is its own table rather than a column on the feed row.
 */
class TankDayNote extends Model
{
    protected $fillable = [
        'tank_id',
        'farm_id',
        'batch_id',
        'note_date',
        'note',
        'created_by',
    ];

    protected $casts = [
        'note_date' => 'date',
    ];

    public function tank()
    {
        return $this->belongsTo(Tank::class, 'tank_id');
    }

    public function batch()
    {
        return $this->belongsTo(TankBatch::class, 'batch_id');
    }

    /**
     * One tank's notes for a crop, keyed by Y-m-d.
     *
     * Keyed so the history response can look a day up rather than scanning, and
     * so the app can index straight into it by the date string it already has.
     *
     * @return array<string, string>
     */
    public static function forTank(int $tankId, ?int $batchId = null): array
    {
        return static::where('tank_id', $tankId)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->get()
            ->mapWithKeys(fn (self $n) => [
                $n->note_date->toDateString() => $n->note,
            ])
            ->all();
    }
}
