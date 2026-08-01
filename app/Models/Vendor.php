<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; 
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Vendor extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = [];


    protected $hidden = [
        'password',
        'remember_token',
    ];

    // protected $casts = [
    //     'password' => 'hashed',
    // ];

    /**
     * ✅ Add is_profile_complete to JSON automatically
     */
    protected $appends = ['is_profile_complete'];


    /**
     * Hash password automatically
     */
    public function setPasswordAttribute($value)
{
    $this->attributes['password'] = bcrypt($value);
}


     /**
     * Auto-generate best_seeds_id before creating vendor
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($vendor) {
            if (empty($vendor->best_seeds_id)) {
                $year = date('Y');
                $lastVendor = self::whereYear('created_at', $year)->orderBy('id', 'desc')->first();

                $nextId = $lastVendor ? intval(substr($lastVendor->best_seeds_id, -2)) + 1 : 1;

                $vendor->best_seeds_id = 'BS' . $year . str_pad($nextId, 2, '0', STR_PAD_LEFT);
            }
        });
    }


    /**
     * ✅ Check if profile is complete
     */
    public function isProfileComplete(): bool
    {
        return !empty($this->name) 
            && !empty($this->mobile) 
            && !empty($this->address) 
            && !empty($this->pincode);
    }


       /**
     * ✅ Accessor for is_profile_complete
     */
    public function getIsProfileCompleteAttribute(): bool
    {
        return $this->isProfileComplete();
    }


//     public function getProfileImageAttribute($value)
// {
//     return $value ? asset('storage/' . $value) : null;
// }


 public function hatcheries()
    {
        return $this->hasMany(Hatchery::class, 'vendor_id');
    }

    public function latestHatchery()
    {
        return $this->hasOne(Hatchery::class, 'vendor_id')->latestOfMany();
    }

    public function updates()
    {
        return $this->hasMany(HatcheryUpdate::class, 'vendor_id');
    }



}

