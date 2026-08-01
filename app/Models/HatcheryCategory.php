<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HatcheryCategory extends Model
{
    use HasFactory;

    protected $table = 'hatchery_categories';

    protected $fillable  = [
        'category_name',
        'type',
        'priority',
        'category_description',
        'category_report',
        'image',
    ];

    // Relationship: One category has many hatcheries
    public function hatcheries()
    {
        return $this->hasMany(Hatchery::class, 'category_id', 'id');
    }


    public function hatcheryLinks()
    {
        return $this->belongsToMany(Hatchery::class, 'hatchery_category_map', 'category_id', 'hatchery_id');
    }

    public function broodstocks()
    {
        return $this->hasMany(Broodstock::class, 'category_id');
    }
}
