<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HatcherySeed extends Model
{
    protected $guarded = [];


    public function hatchery()
    {
        return $this->belongsTo(Hatchery::class, 'hatchery_id', 'id');
    }
    public function category()
    {
        return $this->belongsTo(HatcheryCategory::class,'category_id','id');
    }

    public function location()
    {
        return $this->belongsTo(HatcheryLocation::class);
    }
}

