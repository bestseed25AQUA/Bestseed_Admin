<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HatcheryPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hatchery_posts';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'media_type' => 'string',
    ];

    public function hatchery()
    {
        return $this->belongsTo(Hatchery::class);
    }
}

