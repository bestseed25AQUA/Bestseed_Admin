<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One person's access to one farm.
 *
 * Created when someone redeems a QR, or when an owner (or another member)
 * grants access directly by picking them from the farmer list.
 */
class FarmAccessMember extends Model
{
    protected $fillable = [
        'farm_id',
        'farmer_id',
        'granted_by',
        'manager_id',
        'role',
        'view_access',
        'edit_access',
        'tank_status_access',
        'total_feed_access',
        'create_access',
        'delete_access',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Memberships that still grant access: not revoked, and not expired.
     *
     * Access granted today does not expire — it lasts until someone revokes it,
     * and `addMembers` writes expires_at as NULL. But rows left over from the
     * QR flow DO carry a date, and honouring it costs one clause: a row that
     * says it expired on 23 September must not still open the farm on the 24th.
     * Without this the column was decoration, and the service's own promise of
     * "not revoked, not expired" was only half kept.
     */
    public function scopeLive($query)
    {
        return $query->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForFarmer($query, $farmerId)
    {
        return $query->where('farmer_id', $farmerId);
    }

    public function scopeWithViewAccess($query)
    {
        return $query->where('view_access', 1);
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    /** The farmer who admitted this member — owner, or another member. */
    public function grantedBy()
    {
        return $this->belongsTo(Farmer::class, 'granted_by');
    }

    public function isLive(): bool
    {
        return $this->revoked_at === null;
    }
}
