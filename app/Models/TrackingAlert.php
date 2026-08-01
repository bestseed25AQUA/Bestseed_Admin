<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingAlert extends Model
{
    protected $fillable = [
        'booking_id',
        'driver_id',
        'vendor_id',
        'reason',
        'location_name',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
