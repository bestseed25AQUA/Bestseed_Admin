<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HatcheryLocationBranch extends Model
{
    protected $fillable = [
        'location_id',
        'branch_name',
        'address',
    ];

}
