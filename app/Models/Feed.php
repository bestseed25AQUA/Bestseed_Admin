<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feed extends Model
{
    protected $fillable = [
        'meals',
        'tank_id',
        'farm_id',
        'feed_quantity',
        'feed_date',
        'is_backfill',
        'batch_id',
    ];

    //
}
