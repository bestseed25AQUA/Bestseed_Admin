<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchHatchery extends Model
{
    use HasFactory;

    protected $table = 'branch_hatchery';

    protected $fillable = [
        'hatchery_id',
        'location_id',
        'branch_name',
        'status'
    ];

    // Relationship: Branch belongs to a Hatchery Location
    public function location()
    {
        return $this->belongsTo(HatcheryLocation::class, 'location_id');
    }

    public function branch()
    {
        return $this->belongsTo(HatcheryLocationBranch::class, 'branch_name');
    }
}
