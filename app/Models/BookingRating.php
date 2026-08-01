<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRating extends Model
{
    protected $fillable = [
        'booking_id',
        'farmer_id',
        'rating',
        'message',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }
}
