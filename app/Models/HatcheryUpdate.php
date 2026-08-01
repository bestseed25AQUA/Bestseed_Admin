<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HatcheryUpdate extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'hashtags' => 'array',
        'media_files' => 'array',
        'media_types' => 'array',
    ];

    /**
     * Relation with User (Vendor)
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }


        public function hatchery()
    {
        return $this->belongsTo(Hatchery::class, 'hatchery_id');
    }

    public function location()
    {
        return $this->belongsTo(HatcheryLocation::class, 'location_id');
    }

}
