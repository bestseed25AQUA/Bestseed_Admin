<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverOtp extends Model
{
    protected $fillable = [
        'driver_id',
        'otp_code',
        'verified',
        'expires_at'
    ];
}
