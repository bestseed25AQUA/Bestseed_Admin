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

    /**
     * Screens an announcement can be aimed at, keyed by the stored value.
     *
     * An empty key is the default: shown in the app's general announcement
     * popup rather than on one screen. `farm_management` replaced the farm
     * management BANNER — a banner is scenery, and a seasonal notice about
     * stocking dates was being scrolled past. It is now a popup the farmer
     * sees each time the screen opens.
     */
    public const SCREENS = [
        ''                => 'General (announcements popup)',
        'farm_management' => 'Farm Management',
    ];

    public function scopeForAudience($query, string $audience)
    {
        return $query->where('audience', $audience);
    }

    /**
     * Announcements aimed at one screen.
     *
     * The general popup must NOT pick these up, or a farm-management notice
     * would greet the farmer on the home screen as well.
     */
    public function scopeForScreen($query, string $screen)
    {
        return $query->where('screen', $screen);
    }

    /** Announcements with no screen of their own — the general popup. */
    public function scopeGeneral($query)
    {
        return $query->where(fn ($q) => $q->whereNull('screen')->orWhere('screen', ''));
    }

    public function getScreenLabelAttribute(): string
    {
        return self::SCREENS[(string) $this->screen] ?? ucfirst((string) $this->screen);
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
