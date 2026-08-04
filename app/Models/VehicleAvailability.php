<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleAvailability extends Model
{
    protected $table = 'vehicle_availability';

    protected $fillable = [
        'vehicle_name',
        'hatchery_id',
        'vendor_id',
        'category_id',
        'location_id',
        'location_ids',
        'price',
        'broodstock_count',
        'description',
        'start_time',
        'end_time',
        'start_date',
        'end_date',
        'available_on',
        'available_space',
        'is_active',
    ];

    protected $casts = [
        'location_ids' => 'array',
        'available_on' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'is_active' => 'boolean',
    ];

    public function hatchery()
    {
        return $this->belongsTo(Hatchery::class, 'hatchery_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function category()
    {
        return $this->belongsTo(HatcheryCategory::class, 'category_id');
    }

    public function gallery()
    {
        return $this->hasMany(VehicleGallery::class)->orderBy('sort_order');
    }

    public function selectedHatchery()
    {
        return $this->belongsTo(Hatchery::class, 'selected_hatchery_id');
    }

    public function location()
    {
        return $this->belongsTo(HatcheryLocation::class, 'location_id');
    }

    /**
     * Has the availability window closed?
     *
     * The end date is the LAST day the vehicle is available, so a vehicle
     * ending 01/08 is still offered all through 01/08 and only drops off on
     * 02/08. A missing end_date means "no fixed end" and never expires.
     *
     * Derived rather than stored: no scheduled job to run, and an admin who
     * extends the end date sees it go back to Active immediately.
     */
    public function getIsExpiredAttribute(): bool
    {
        if (empty($this->end_date)) {
            return false;
        }

        return $this->end_date->endOfDay()->isPast();
    }

    /**
     * Only vehicles whose availability window is still open — the query-side
     * counterpart of [getIsExpiredAttribute], used by the user-app API.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
                ->orWhereDate('end_date', '>=', now()->toDateString());
        });
    }
}
