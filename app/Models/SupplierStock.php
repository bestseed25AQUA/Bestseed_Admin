<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierStock extends Model
{
    use HasFactory;

     protected $guarded = [];

    protected $casts = [
        'packing_start_date' => 'date',
    ];

    // 🔹 Relationships
    public function hatchery()
    {
        return $this->belongsTo(Hatchery::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }


}
