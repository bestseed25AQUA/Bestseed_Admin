<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One recorded change to a farm.
 *
 * Written by [\App\Services\FarmActivityLogger] and never edited afterwards —
 * a log that can be corrected is not evidence of anything.
 */
class FarmActivity extends Model
{
    /** Areas of Farm Management an entry can belong to. */
    public const CATEGORY_FARM   = 'farm';
    public const CATEGORY_TANK   = 'tank';
    public const CATEGORY_FEED   = 'feed';
    public const CATEGORY_STORE  = 'store';
    public const CATEGORY_ACCESS = 'access';

    /** What happened. */
    public const ACTION_CREATED   = 'created';
    public const ACTION_UPDATED   = 'updated';
    public const ACTION_DELETED   = 'deleted';
    public const ACTION_ACTIVATED = 'activated';
    public const ACTION_HARVESTED = 'harvested';
    public const ACTION_GRANTED   = 'granted';
    public const ACTION_REVOKED   = 'revoked';

    /** How long each audience may look back. */
    public const APP_WINDOW_DAYS   = 15;
    public const ADMIN_WINDOW_DAYS = 30;

    protected $fillable = [
        'farm_id',
        'tank_id',
        'tank_name',
        'category',
        'action',
        'description',
        'changes',
        'actor_type',
        'actor_id',
        'actor_name',
        'actor_mobile',
        'actor_role',
    ];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    /** Entries from the last $days days, newest first. */
    public function scopeRecent($query, int $days)
    {
        return $query
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function scopeForFarm($query, int $farmId)
    {
        return $query->where('farm_id', $farmId);
    }

    /** Every category that actually appears, for building filter chips. */
    public static function categories(): array
    {
        return [
            self::CATEGORY_FARM   => 'Farm',
            self::CATEGORY_TANK   => 'Tanks',
            self::CATEGORY_FEED   => 'Feed',
            self::CATEGORY_STORE  => 'Store',
            self::CATEGORY_ACCESS => 'Access',
        ];
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::categories()[$this->category] ?? ucfirst($this->category);
    }

    /** Bootstrap contextual colour for the admin table. */
    public function getActionColourAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED, self::ACTION_GRANTED   => 'success',
            self::ACTION_DELETED, self::ACTION_REVOKED   => 'danger',
            self::ACTION_HARVESTED                       => 'secondary',
            self::ACTION_ACTIVATED                       => 'info',
            default                                      => 'primary',
        };
    }

    /**
     * Who did it, as one readable string.
     *
     * The mobile is part of the identity here, not decoration: two people
     * called Ramesh on one farm is the ordinary case, and the number is what
     * tells them apart.
     */
    public function getActorLabelAttribute(): string
    {
        $name = trim((string) $this->actor_name) ?: 'Unknown';

        return $this->actor_mobile ? "{$name} ({$this->actor_mobile})" : $name;
    }
}
