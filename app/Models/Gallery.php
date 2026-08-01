<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * 🔹 Polymorphic relation (Seed, Hatchery, Booking, etc.)
     */
    public function loadable()
    {
        return $this->morphTo();
    }

    /**
     * 🔹 Polymorphic relation (Vendor, User/Farmer, Admin)
     */
    public function uploader()
    {
        return $this->morphTo();
    }


}
