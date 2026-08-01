<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BroadStock extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'available_date' => 'date',
        'packing_date' => 'date',
    ];

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

