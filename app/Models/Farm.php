<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Farm extends Model
{
    //
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'farm_name',
        'farmer_id',
        'stocking_date',
        'no_of_tanks',
        'store',
        'low_feed_limit',
    ];

    // Relation: One Farm has many Images
    public function images()
    {
        //return $this->hasMany(FarmImage::class);
        return $this->hasOne(FarmImage::class); 
    }
}
