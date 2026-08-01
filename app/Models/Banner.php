<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $guarded  = [];

      protected $casts = [
        'status' => 'boolean',
    ];



     public function category()
    {
        return $this->belongsTo(HatcheryCategory::class, 'category_id', 'id');
    }

}
