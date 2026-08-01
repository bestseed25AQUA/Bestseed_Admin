<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WantedCrop extends Model
{
    use HasFactory;

    protected $table = 'wanted_crops';

    protected $guarded = [];


     protected $casts = [
    'date' => 'date:Y-m-d', // now $item->date is automatically a Carbon instance
];
    // Relationship with Farmer
    public function farmer()
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    // Relationship with Vendor/Hatchery
    // public function hatchery()
    // {
    //     return $this->belongsTo(Vendor::class, 'hatchery_id');
    // }


        public function hatchery()
        {
            return $this->belongsTo(Hatchery::class, 'hatchery_id');
        }


            public function category()
        {
            return $this->belongsTo(HatcheryCategory::class, 'category_id');
        }

        public function location()
        {
            return $this->belongsTo(HatcheryLocation::class, 'location_id');
        }

        public function marketPrice()
        {
            return $this->belongsTo(MarketPrice::class, 'price_id');
        }



                public function vendor()
        {
            return $this->belongsTo(Vendor::class, 'vendor_id');
        }



}
