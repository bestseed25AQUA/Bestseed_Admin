<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HatcheryLocation extends Model
{
    use HasFactory;

    protected $table = 'hatchery_locations';

    protected $fillable = [
        'location_name',
        'priority',
        'longitude',
        'latitude',
        'farmer_id',
        'full_address',
    ];

    // Relationship: One location has many hatcheries
    public function hatcheries()
    {
        return $this->hasMany(Hatchery::class, 'location_id', 'id');
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    public function branches()
    {
        return $this->hasMany(BranchHatchery::class, 'location_id');
    }


}
