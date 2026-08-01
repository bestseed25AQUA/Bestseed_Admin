<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Audiences the admin dropdown offers, keyed by the stored value. */
    public const AUDIENCES = [
        'user'   => 'User',
        'driver' => 'Driver',
        'vendor' => 'Vendor',
    ];

    public function reads()
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAudience($query, string $audience)
    {
        return $query->where('audience', $audience);
    }

    public function getAudienceLabelAttribute(): string
    {
        return self::AUDIENCES[$this->audience] ?? ucfirst((string) $this->audience);
    }

    /** Absolute URL for the app, or null when no image was uploaded. */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset($this->image) : null;
    }
}
