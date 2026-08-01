<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hatchery extends Model
{
    use HasFactory;

    protected $fillable = [
        'hatchery_name',
        'logo',
        'hatchery_category_map',     // pivot table
        'category_id',
        'location_id',
        'lat',
        'lng',
        'vendor_id',
        'opening_time',
        'closing_time',
        'image',
        'brand_id',
        'available_on',
        'is_spot',
        'is_vehicle',
        'category_prices',
        'category_details',
        'hatchery_status',
        'is_active',
        'price',
        'broodstock_count',
        'no_of_pieces',
        'description',
        'banner_image',
        'categories',
        'locations',
        'status',
        'selected_hatchery_id',
        'branch_id',
        'report',
        'unique_id',
        'call_number',
        'whatsapp_number',
        'facebook_link',
    ];

    protected $casts = [
        'opening_time' => 'datetime:H:i',
        'closing_time' => 'datetime:H:i',
        'category_prices' => 'array',
        'category_details' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Only ACTIVE hatcheries — used by every customer-facing query so Inactive
     * hatcheries (and their data) are hidden from users. Admin/vendor queries
     * deliberately do NOT use this scope so they can still see & reactivate them.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Real hatcheries only — excludes the separate rows SpotHatcheryController
     * creates with is_spot = 1.
     *
     * Spot hatcheries live in this same table as their own records, so any
     * dropdown that doesn't exclude them lists a second "Eastcoast",
     * "RAMA HACHERY" etc. next to the real one. The Hatcheries admin page has
     * always filtered them out; the pickers had not, which is what made them
     * look like duplicates.
     *
     * is_spot is nullable, so NULL must be treated as "not a spot hatchery" —
     * a bare where('is_spot', 0) would silently drop those rows.
     */
    public function scopeExcludingSpot($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('is_spot')->orWhere('is_spot', 0);
        });
    }

    /**
     * Label for admin pickers.
     *
     * A hatchery is stored as one row per category/vendor combination, so
     * several rows legitimately share a hatchery_name (Mahalakshmi has three,
     * one per category). Showing the bare name makes them indistinguishable —
     * this appends whatever distinguishes them.
     */
    public function getPickerLabelAttribute(): string
    {
        $label = $this->hatchery_name ?: ('Hatchery #' . $this->id);

        $parts = array_filter([
            $this->category->category_name ?? null,
            $this->location->location_name ?? null,
        ]);

        return $parts ? $label . ' — ' . implode(', ', $parts) : $label;
    }

    public const STATUS_MAP = [
        1 => ['label' => 'Available', 'class' => 'bg-success'],
        2 => ['label' => 'Coming Soon', 'class' => 'bg-info'],
        3 => ['label' => 'Upcoming', 'class' => 'bg-primary'],
        4 => ['label' => 'Shortly Available', 'class' => 'bg-warning'],
        5 => ['label' => 'Closed', 'class' => 'bg-danger'],
    ];

    public function getStatusLabelAttribute(): string
    {
        $statusValue = $this->attributes['hatchery_status'] ?? $this->attributes['status'] ?? null;
        return self::STATUS_MAP[$statusValue]['label'] ?? 'Coming Soon';
    }

    public function getStatusClassAttribute(): string
    {
        $statusValue = $this->attributes['hatchery_status'] ?? $this->attributes['status'] ?? null;
        return self::STATUS_MAP[$statusValue]['class'] ?? 'bg-secondary';
    }

    public function getOverallStatusCodeAttribute()
    {
        return $this->attributes['hatchery_status'] ?? $this->attributes['status'] ?? null;
    }
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function category()
    {
        return $this->belongsTo(HatcheryCategory::class, 'category_id');
    }

    public function categories()
    {
        return $this->belongsToMany(HatcheryCategory::class, 'hatchery_category_map', 'hatchery_id', 'category_id');
    }
    public function units()
    {
        return $this->hasMany(BranchHatchery::class, 'hatchery_id');
    }

    public function broodstock()
    {
        return $this->hasMany(Broodstock::class, 'hatchery_id');
    }

    public function prices()
    {
        return $this->hasMany(MarketPrice::class, 'hatchery_id');
    }

    public function location()
    {
        return $this->belongsTo(HatcheryLocation::class, 'location_id');
    }

    public function seeds()
    {
        return $this->hasMany(Seed::class);
    }

    public function updates()
    {
        return $this->hasMany(HatcheryUpdate::class);
    }

    public function fishCategory()
    {
        return $this->belongsTo(HatcheryCategory::class, 'brand_id');
    }
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'hatchery_id');
    }

    public function selectedHatchery()
    {
        return $this->belongsTo(Hatchery::class, 'selected_hatchery_id');
    }

    public function branch()
    {
        return $this->belongsTo(HatcheryLocationBranch::class, 'branch_id');
    }

    public function getImageAttribute($value)
    {
        if (empty($value)) {
            return [];
        }

        $decoded = is_array($value) ? $value : json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_map(function ($path) {
            // If already a full URL (like https://...), return as-is
            if (str_starts_with($path, 'http')) {
                return $path;
            }

            // If path starts with 'uploads/', return with asset() to add full URL
            if (str_starts_with($path, 'uploads/')) {
                return asset($path);
            }

            // Fallback - if only filename, use category_media as default
            return asset('uploads/category_media/' . basename($path));
        }, $decoded);
    }

}
