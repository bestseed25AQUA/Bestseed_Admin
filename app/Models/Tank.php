<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tank extends Model
{
    //
     protected $fillable = [
        'tank_name',
        'farm_id',
         'status',
        'stocking_date',
        'meals',
        'store',
        
    ];

}
