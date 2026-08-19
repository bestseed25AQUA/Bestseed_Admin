<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager extends Model
{
     protected $fillable = [
        'farm_id',
        'name',
        'phone',
        'create_access',
        'view_access',
        'edit_access',
        'delete_access',
        'is_partner',
    ];

    /** The farm this person was attached to (null on legacy pre-farm_id rows). */
    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    /** The QR grants that created or refreshed this person's access. */
    public function accessGrants()
    {
        return $this->hasMany(FarmAccessGrant::class, 'manager_id');
    }

    /** 'partner' or 'manager' — the flag is stored, the word is derived. */
    public function getRoleLabelAttribute(): string
    {
        return $this->is_partner ? 'partner' : 'manager';
    }

    public function scopeManagers($query)
    {
        return $query->where('is_partner', 0);
    }

    public function scopePartners($query)
    {
        return $query->where('is_partner', 1);
    }
}
