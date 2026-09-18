<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\FarmSubscription;
use Carbon\Carbon;

/**
 * Who may create another farm, and what the app should be told about it.
 *
 * Single source of truth, because the question is asked in three places that
 * must never disagree: the app asks before opening the add-farm form, the API
 * asks again before actually creating one, and the admin panel asks when
 * showing a farmer's standing. A farmer allowed through the first check and
 * refused by the second would fill in a long form for nothing.
 */
class SubscriptionService
{
    /** How many farms a farmer may own without paying. */
    public function freeLimit(): int
    {
        return max(0, (int) config('subscriptions.free_farm_limit', 2));
    }

    /**
     * Farms this farmer OWNS.
     *
     * Farms shared with them as a manager or partner belong to somebody else's
     * allowance; counting those would refuse a farmer their own second farm
     * because two neighbours had added them as a helper.
     *
     * Soft-deleted farms are excluded by the model's global scope, so a farm
     * the farmer deleted gives the slot back.
     */
    public function ownedFarmCount(int $farmerId): int
    {
        return Farm::where('farmer_id', $farmerId)->count();
    }

    /** The live subscription for this farmer, or null. */
    public function activeFor(int $farmerId): ?FarmSubscription
    {
        return FarmSubscription::where('farmer_id', $farmerId)
            ->active()
            ->orderByDesc('expires_at')
            ->first();
    }

    /**
     * The most recent subscription of any state, live or not.
     *
     * What the app shows when the allowance is used up: "your plan ended on
     * the 3rd" is a far more useful thing to read than "you need a plan".
     */
    public function latestFor(int $farmerId): ?FarmSubscription
    {
        return FarmSubscription::where('farmer_id', $farmerId)
            ->orderByDesc('expires_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * May this farmer create another farm?
     *
     * Under the free limit, or holding a live subscription. Note the order:
     * the cheap count runs first, so the common case never touches the
     * subscriptions table.
     */
    public function canCreateFarm(int $farmerId): bool
    {
        if ($this->ownedFarmCount($farmerId) < $this->freeLimit()) {
            return true;
        }

        return $this->activeFor($farmerId) !== null;
    }

    /**
     * Why a farmer was refused, in words meant for them.
     *
     * Distinguishes "you never had a plan" from "yours ran out", because the
     * second needs a date and reads as an accusation without one.
     */
    public function refusalMessage(int $farmerId): string
    {
        $limit  = $this->freeLimit();
        $latest = $this->latestFor($farmerId);

        if ($latest && $latest->is_expired) {
            return "Your subscription ended on {$latest->expires_at->format('d M Y')}. "
                 . "Renew it to add more farms. Your existing farms are unaffected.";
        }

        return "You can create up to {$limit} " . ($limit === 1 ? 'farm' : 'farms')
             . " for free. Subscribe to add more.";
    }

    /**
     * The plan catalogue, shaped for the app's bottom sheet.
     *
     * Served rather than hardcoded in the app so a price change does not need
     * a store release.
     */
    public function plans(): array
    {
        $symbol = config('subscriptions.currency_symbol', '₹');
        $plans  = [];

        foreach ((array) config('subscriptions.plans', []) as $key => $plan) {
            $amount = (float) ($plan['amount'] ?? 0);
            $months = (int) ($plan['months'] ?? 1);

            $plans[] = [
                'key'             => $key,
                'label'           => $plan['label'] ?? $key,
                'months'          => $months,
                'amount'          => $amount,
                'currency'        => config('subscriptions.currency', 'INR'),
                'currency_symbol' => $symbol,
                // Preformatted so every surface renders the price identically
                // and no client has to guess at decimals or separators.
                'price_label'     => $symbol . rtrim(rtrim(number_format($amount, 2, '.', ','), '0'), '.'),
                // What a month of this plan costs, for the "best value" badge.
                // Null-safe: a zero-month plan would divide by zero.
                'per_month'       => $months > 0 ? round($amount / $months, 2) : null,
            ];
        }

        return $plans;
    }

    /**
     * Everything the app needs to decide what to show, in one call.
     *
     * Deliberately one endpoint rather than several: the add-farm button must
     * make exactly one decision, and splitting the inputs across round trips
     * is how a button ends up briefly wrong.
     */
    public function statusFor(int $farmerId): array
    {
        $owned      = $this->ownedFarmCount($farmerId);
        $limit      = $this->freeLimit();
        $active     = $this->activeFor($farmerId);
        $subscription = $active ?: $this->latestFor($farmerId);

        return [
            'owned_farms'       => $owned,
            'free_limit'        => $limit,
            'free_remaining'    => max(0, $limit - $owned),
            'can_create_farm'   => $owned < $limit || $active !== null,
            // True only when a PLAN is what stands between them and a new
            // farm, so the app shows the sheet instead of a generic error.
            'needs_subscription' => $owned >= $limit && $active === null,
            'message'           => $owned < $limit || $active
                ? null
                : $this->refusalMessage($farmerId),
            'subscription'      => $subscription ? $this->present($subscription) : null,
            'plans'             => $this->plans(),
        ];
    }

    /** One subscription as the API exposes it. */
    public function present(FarmSubscription $subscription): array
    {
        return [
            'id'              => $subscription->id,
            'plan_key'        => $subscription->plan_key,
            'plan_label'      => $subscription->plan_label,
            'amount'          => (float) $subscription->amount,
            'months'          => $subscription->months,
            'starts_at'       => $subscription->starts_at?->toDateString(),
            'expires_at'      => $subscription->expires_at?->toDateString(),
            'expires_on'      => $subscription->expires_at?->format('d M Y'),
            'days_remaining'  => $subscription->days_remaining,
            'state'           => $subscription->state,
            'is_active'       => $subscription->is_active,
            'is_expired'      => $subscription->is_expired,
            'is_expiring_soon' => $subscription->is_expiring_soon,
            'cancelled_at'    => $subscription->cancelled_at?->toIso8601String(),
        ];
    }

    /**
     * Record a purchase.
     *
     * Renewals extend rather than overlap: buying three months while ten days
     * remain gives three months and ten days, not three months from today. The
     * farmer paid for a duration and must get all of it.
     */
    public function subscribe(
        int $farmerId,
        string $planKey,
        ?string $startsAt = null,
        ?string $notes = null,
        ?int $createdBy = null
    ): FarmSubscription {
        $plan = config("subscriptions.plans.{$planKey}");

        if (!$plan) {
            throw new \InvalidArgumentException("Unknown subscription plan [{$planKey}].");
        }

        $months = (int) ($plan['months'] ?? 1);

        // Where the new term begins: the day after the current one ends when
        // renewing early, otherwise the date the admin chose, otherwise today.
        $active = $this->activeFor($farmerId);

        $start = $startsAt
            ? Carbon::parse($startsAt)->startOfDay()
            : ($active
                ? $active->expires_at->copy()->addDay()->startOfDay()
                : Carbon::today());

        return FarmSubscription::create([
            'farmer_id'  => $farmerId,
            'plan_key'   => $planKey,
            'plan_label' => $plan['label'] ?? $planKey,
            'amount'     => $plan['amount'] ?? 0,
            'months'     => $months,
            'starts_at'  => $start->toDateString(),
            // addMonths then subDay: a one-month plan starting on the 1st runs
            // to the 30th/31st inclusive, not into the next month's 1st.
            'expires_at' => $start->copy()->addMonths($months)->subDay()->toDateString(),
            'notes'      => $notes,
            'created_by' => $createdBy,
        ]);
    }
}
