<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $guarded = [];

     protected $casts = [
        'seed_updates'       => 'boolean',
        'hatchery_updates'   => 'boolean',
        'broodstock_updates' => 'boolean',
        'vehicle_updates'    => 'boolean',
    ];

    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

    //  public function farmer()
    // {
    //     return $this->belongsTo(Farmer::class);
    // }

    public function vendor()
    {
        
        return $this->belongsTo(Vendor::class);
    }
}
