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
}
