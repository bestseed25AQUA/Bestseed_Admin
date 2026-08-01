<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Driver extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'mobile',
        'status',
        'profile_image',
        'location_permission',
        'battery_optimization_disabled',
        'permissions_updated_at',
    ];

    protected $casts = [
        'location_permission' => 'boolean',
        'battery_optimization_disabled' => 'boolean',
        'permissions_updated_at' => 'datetime',
    ];


    public function bookings()
    {
        return $this->hasMany(Booking::class, 'driver_id');
    }

    public function bookingsByMobile()
    {
        return $this->hasMany(Booking::class, 'driver_mobile', 'mobile');
    }

}
