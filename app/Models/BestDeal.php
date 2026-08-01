<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BestDeal extends Model
{
    use HasFactory;

    protected $table = 'best_deals';

    protected $guarded = [];

    protected $casts = [
        'media_files' => 'array',
        'media_types' => 'array',
    ];
}
