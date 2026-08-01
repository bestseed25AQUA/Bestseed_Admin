<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Farmer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = [
        'remember_token',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
    ];

    public function otps()
    {
        return $this->hasMany(UserOtp::class);
    }

    public function locations()
    {
        return $this->hasMany(HatcheryLocation::class, 'farmer_id');
    }

    public function latestLocation()
    {
        return $this->hasOne(HatcheryLocation::class, 'farmer_id')->latestOfMany();
    }
}

//     public function galleries()
// {
//     return $this->morphMany(Gallery::class, 'loadable');
// }


