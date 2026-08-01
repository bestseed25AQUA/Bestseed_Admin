<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Broodstock extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'images'          => 'array',
        'available_date'  => 'date',
        'packing_date'    => 'date',
        'imported_date' => 'date',
    ];


    public function vendor()
{
    return $this->belongsTo(\App\Models\Vendor::class, 'vendor_id');
}



    // public function vendor()
    // {
    //     return $this->belongsTo(Vendor::class, 'vendor_id');
    // }

    public function category()
    {
        return $this->belongsTo(HatcheryCategory::class, 'category_id');
    }

    public function location()
    {
        return $this->belongsTo(HatcheryLocation::class, 'location_id');
    }

    public function supplier()
    {
        return $this->belongsTo(SupplierStock::class, 'supplier_name', 'supplier_name');
    }

    public function hatchery()
    {
        return $this->belongsTo(Hatchery::class, 'hatchery_id', 'id');
    }
}
