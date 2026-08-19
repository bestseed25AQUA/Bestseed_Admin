<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class FarmAccessGrant extends Model
{
    protected $fillable = [
        'farm_id',
        'issued_by',
        'manager_id',
        'token',
        'pin_secret',
        'role',
        'view_access',
        'edit_access',
        'create_access',
        'delete_access',
        'duration_days',
        'expires_at',
        'redeemed_at',
        'redeemed_by',
        'revoked_at',
        'pin_attempts',
    ];

    /** The stored ciphertext must never be serialised into a response. */
    protected $hidden = ['pin_secret'];

    /** Plaintext PIN, decrypted for the issuing farmer only. */
    public function pin(): ?string
    {
        try {
            return Crypt::decryptString($this->pin_secret);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected $casts = [
        'expires_at'  => 'datetime',
        'redeemed_at' => 'datetime',
        'revoked_at'  => 'datetime',
    ];

    /**
     * Grants that can still be used: redeemed by someone, not revoked by the
     * farmer, and not past their expiry. Everything that asks "does this person
     * still have access?" goes through here.
     */
    public function scopeLive($query)
    {
        return $query
            ->whereNotNull('redeemed_at')
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /** Grants redeemed by a specific farmer (the person who scanned the QR). */
    public function scopeRedeemedBy($query, $farmerId)
    {
        return $query->where('redeemed_by', $farmerId);
    }

    /** Grants that carry read access to the farm. */
    public function scopeWithViewAccess($query)
    {
        return $query->where('view_access', 1);
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function manager()
    {
        return $this->belongsTo(Manager::class, 'manager_id');
    }

    /** A grant is usable until it is revoked or its expiry passes. */
    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function statusLabel(): string
    {
        if ($this->revoked_at !== null) {
            return 'revoked';
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'expired';
        }
        if ($this->redeemed_at !== null) {
            return 'active';
        }

        return 'pending';
    }

    /** Whole days left before expiry; 0 once it has lapsed. */
    public function daysRemaining(): int
    {
        if ($this->expires_at === null) {
            return 0;
        }

        // Round up: a grant with 29.9 days left has 30 days remaining, not 29.
        return max(0, (int) ceil(now()->diffInDays($this->expires_at, false)));
    }
}
