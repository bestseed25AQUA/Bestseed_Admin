<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A package admin sells: so many farms, for so many months, at a price.
 *
 * Written in the admin panel rather than a config file, so a new offer — "2
 * months, 4 farms" — is a form rather than a deploy.
 *
 * A plan is never deleted, only retired. Subscriptions already sold point at
 * it, and their history has to keep reading correctly.
 */
class SubscriptionPlan extends Model
{
    protected $fillable = [
        'key',
        'label',
        'months',
        'farm_limit',
        'amount',
        'reminders',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'months'     => 'integer',
        'farm_limit' => 'integer',
        'amount'     => 'decimal:2',
        'reminders'  => 'array',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions()
    {
        return $this->hasMany(FarmSubscription::class, 'plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('months');
    }

    /**
     * Days before expiry to warn, largest first.
     *
     * A long plan warns earlier and more often, because there is more to lose
     * by letting it lapse unnoticed; a 30-day warning on a one-month plan would
     * fire the day it was sold.
     */
    public function reminderDays(): array
    {
        $days = array_filter(
            array_map('intval', (array) ($this->reminders ?? [])),
            fn ($d) => $d > 0
        );

        if ($days === []) {
            $days = $this->months >= 6 ? [30, 15, 7, 3, 1] : [7, 3, 1];
        }

        rsort($days);

        return array_values(array_unique($days));
    }

    /** When this plan starts being shown as expiring soon. */
    public function warnFromDays(): int
    {
        return $this->reminderDays()[0] ?? 7;
    }

    /** "1 Month · 1 farm · ₹199" — one label every screen can show. */
    public function getSummaryAttribute(): string
    {
        $symbol = config('subscriptions.currency_symbol', '₹');

        return sprintf(
            '%s · %d %s · %s%s',
            $this->label,
            $this->farm_limit,
            $this->farm_limit === 1 ? 'farm' : 'farms',
            $symbol,
            rtrim(rtrim(number_format((float) $this->amount, 2, '.', ','), '0'), '.')
        );
    }
}
