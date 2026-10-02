<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A farmer asking to be subscribed, from inside the app.
 *
 * The subscription screen offers Call and WhatsApp, both of which need somebody
 * to pick up. This is the third way: leave a request, and it waits in admin
 * until it is dealt with. Nothing is charged and nothing is granted — it is a
 * message with the farm and package already attached, so admin does not have to
 * ask which farm they meant.
 */
class SubscriptionRequest extends Model
{
    public const PENDING   = 'pending';
    public const CONTACTED = 'contacted';
    public const DONE      = 'done';
    public const DECLINED  = 'declined';

    protected $fillable = [
        'farmer_id',
        'farm_id',
        'plan_id',
        'plan_label',
        'message',
        'status',
        'admin_note',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    public function farmer()
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    /** The farm it is about — null when they are asking for their first extra. */
    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::PENDING, self::CONTACTED]);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::PENDING);
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->status, [self::PENDING, self::CONTACTED], true);
    }

    /** Bootstrap class for the row, so an untouched request stands out. */
    public function getRowClassAttribute(): string
    {
        return match ($this->status) {
            self::PENDING   => 'table-warning',
            self::DECLINED  => 'table-secondary',
            default         => '',
        };
    }
}
