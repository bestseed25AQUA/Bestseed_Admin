<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Farm extends Model
{
    //
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'farm_name',
        'farmer_id',
        'status',
        'stocking_date',
        'no_of_tanks',
        'store',
        'low_feed_limit',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    /** Farms an owner or grantee is allowed to open in the app. */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    // Relation: One Farm has many Images
    public function images()
    {
        //return $this->hasMany(FarmImage::class);
        return $this->hasOne(FarmImage::class);
    }

    /** The farmer who owns this farm. */
    public function farmer()
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    public function tanks()
    {
        return $this->hasMany(Tank::class, 'farm_id');
    }

    /**
     * Farms a farmer is allowed to see: the ones they own, plus the ones they
     * scanned into with a still-live grant that carries view access.
     *
     * Managers and partners are farmers too — they log in with the same token
     * and are told apart only by the grant they redeemed, so ownership and
     * granted access have to be one query, not two code paths.
     */
    public function scopeAccessibleBy($query, $farmerId)
    {
        return $query->active()->where(function ($q) use ($farmerId) {
            $q->where('farmer_id', $farmerId)
              ->orWhereIn('id', FarmAccessMember::query()
                  ->select('farm_id')
                  ->forFarmer($farmerId)
                  ->withViewAccess()
                  ->live());
        });
    }

    /** Everyone who currently holds access to this farm. */
    public function accessMembers()
    {
        return $this->hasMany(FarmAccessMember::class, 'farm_id');
    }
}
