<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketPrice extends Model
{
    use HasFactory;

    protected $guarded = [];

public function category()
{
    return $this->belongsTo(HatcheryCategory::class, 'category_id');
}

public function location()
{
    return $this->belongsTo(HatcheryLocation::class, 'location_id');
}

}
