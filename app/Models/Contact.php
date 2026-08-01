<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    /**
     * Fixed contact "slots". Each label maps to a specific place in the user
     * app: Main Office → Home, Profile Help → Profile/Help, Booking Help →
     * Booking details Call. Used to validate the admin form and keep the app
     * mapping reliable.
     */
    public const LABELS = [
        'Main Office',
        'Profile Help',
        'Booking Help',
    ];

    protected $fillable = [
        'phone',
        'whatsapp',
        'label',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
}
