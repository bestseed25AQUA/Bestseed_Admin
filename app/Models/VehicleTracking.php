<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'location_name',
        'lat',
        'lng',
        'status',
        'title',
        'subtitle',
        'reached_at',
        'order',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'reached_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
