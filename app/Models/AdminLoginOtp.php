<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLoginOtp extends Model
{
    protected $fillable = [
        'user_id',
        'otp_code',
        'verified',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}


