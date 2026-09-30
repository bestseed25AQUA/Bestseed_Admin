<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A farmer's paid window for creating farms beyond the free allowance.
 *
 * "Active" is derived from the dates, never stored. A stored flag would need
 * something to run in order to stay true, and the day that something stops
 * running every lapsed subscription silently keeps working.
 */
class FarmSubscription extends Model
{
    protected $fillable = [
        'farmer_id',
        'plan_id',
        'plan_key',
        // Snapshot of what this sale granted — see the migration. Never read
        // through to the plan, or editing a package would retroactively change
        // what somebody already bought.
        'farm_limit',
        'plan_label',
        'amount',
        'months',
        'starts_at',
        'expires_at',
        'cancelled_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'starts_at'    => 'date',
        'expires_at'   => 'date',
        'cancelled_at' => 'datetime',
        'amount'       => 'decimal:2',
        'months'       => 'integer',
        'farm_limit'   => 'integer',
    ];

    public function farmer()
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    /** The package this was sold from. Null on rows predating the catalogue. */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function reminders()
    {
        return $this->hasMany(SubscriptionReminder::class, 'subscription_id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    /**
     * Subscriptions granting access right now.
     *
     * `expires_at` is inclusive: a subscription expiring today is still good
     * today and stops tomorrow. Anything else takes a day off what was sold.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at')
            ->whereDate('starts_at', '<=', now()->toDateString())
            ->whereDate('expires_at', '>=', now()->toDateString());
    }

    /** Ran out on its own, as opposed to being cancelled. */
    public function scopeExpired($query)
    {
        return $query->whereNull('cancelled_at')
            ->whereDate('expires_at', '<', now()->toDateString());
    }

    public function scopeCancelled($query)
    {
        return $query->whereNotNull('cancelled_at');
    }

    /** Active, and with no more than $days left. */
    public function scopeExpiringWithin($query, int $days)
    {
        return $query->active()
            ->whereDate('expires_at', '<=', now()->addDays($days)->toDateString());
    }

    /**
     * Active, and inside its OWN plan's warning window.
     *
     * The threshold differs per plan, so it is built as a CASE over plan_key
     * rather than a single number. Done in SQL rather than by filtering a
     * fetched collection so the admin list can still paginate and count at the
     * database.
     *
     * Matches [getIsExpiringSoonAttribute] exactly — the list, the summary
     * counts and each row's colour must never disagree about the same row.
     */
    public function scopeExpiringSoon($query)
    {
        $fallback = (int) config('subscriptions.expiring_soon_days', 7);

        $case     = 'CASE `plan_key`';
        $bindings = [];

        // From the catalogue admin writes. It read config before, so a package
        // created in the panel had no case arm and fell to the 7-day fallback —
        // a 12-month package would have gone amber a week before expiry instead
        // of the month its own reminders asked for.
        foreach (SubscriptionPlan::all() as $plan) {
            $case .= ' WHEN ? THEN ?';
            $bindings[] = $plan->key;
            $bindings[] = $plan->warnFromDays();
        }

        $case .= ' ELSE ? END';
        $bindings[] = $fallback;

        return $query->active()
            ->whereRaw("DATEDIFF(`expires_at`, CURDATE()) <= ({$case})", $bindings);
    }

    // ── Derived state ───────────────────────────────────────────────────────

    /**
     * Whole days until it lapses. 0 on the last day, negative once past.
     *
     * Both sides are floored to midnight so the answer does not swing with the
     * time of day: a subscription ending tomorrow reads 1 whether it is asked
     * at 9am or 11pm.
     */
    public function getDaysRemainingAttribute(): int
    {
        if (!$this->expires_at) {
            return 0;
        }

        return Carbon::today()->diffInDays($this->expires_at->copy()->startOfDay(), false);
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function getIsActiveAttribute(): bool
    {
        if ($this->is_cancelled) {
            return false;
        }

        $today = Carbon::today();

        return $this->starts_at
            && $this->expires_at
            && $this->starts_at->startOfDay()->lessThanOrEqualTo($today)
            && $this->expires_at->startOfDay()->greaterThanOrEqualTo($today);
    }

    public function getIsExpiredAttribute(): bool
    {
        return !$this->is_cancelled
            && $this->expires_at
            && $this->expires_at->startOfDay()->lessThan(Carbon::today());
    }

    /**
     * Active but close enough to expiry to be worth flagging.
     *
     * The window is the plan's OWN first reminder, not a fixed number of days.
     * A one-month plan has 30 days the day it is sold, so a flat 30-day window
     * painted it amber and told the farmer it was running out the moment they
     * paid — the warning has to scale with the term it is warning about.
     */
    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->is_active && $this->days_remaining <= $this->warningWindow();
    }

    /**
     * Days before expiry at which this plan starts warning.
     *
     * The largest reminder threshold, so the amber row, the app's banner and
     * the first push all begin on the same day.
     */
    public function warningWindow(): int
    {
        $days = $this->reminderDays();

        return $days ? (int) $days[0] : (int) config('subscriptions.expiring_soon_days', 7);
    }

    /**
     * One word for the row's state, used by both the admin list and the API.
     *
     * Order matters: cancelled beats expired, because a cancelled row should
     * not also be shouted about in red once its date passes.
     */
    public function getStateAttribute(): string
    {
        if ($this->is_cancelled) {
            return 'cancelled';
        }

        if ($this->is_expired) {
            return 'expired';
        }

        return $this->is_expiring_soon ? 'expiring' : 'active';
    }

    /** Bootstrap contextual class for the admin table row. */
    public function getRowClassAttribute(): string
    {
        return match ($this->state) {
            'expired'   => 'table-danger',
            'expiring'  => 'table-warning',
            'cancelled' => 'table-secondary',
            default     => '',
        };
    }

    /**
     * The reminder thresholds this plan should fire, largest first.
     *
     * Read from the package in the catalogue, which admin writes. Falls back to
     * a sensible set when a row points at a package that has since been removed
     * entirely, so an orphaned row still warns rather than going quiet.
     */
    public function reminderDays(): array
    {
        $plan = $this->relationLoaded('plan')
            ? $this->plan
            : SubscriptionPlan::where('key', $this->plan_key)->first();

        if ($plan) {
            return $plan->reminderDays();
        }

        $days = array_map('intval', (array) config(
            "subscriptions.plans.{$this->plan_key}.reminders",
            [7, 3, 2, 1]
        ));
        rsort($days);

        return $days;
    }
}
