<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\FarmSubscription;
use App\Models\SubscriptionPlan;
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
    /** @var array<int, array<int>> */
    private array $writableCache = [];

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
     * EVERY live subscription, newest expiry first.
     *
     * A farmer may hold several at once. Someone on a 1-farm package who needs
     * a second farm buys another package rather than being upgraded in place,
     * so the two run side by side and lapse on their own dates.
     */
    public function activePlansFor(int $farmerId)
    {
        return FarmSubscription::where('farmer_id', $farmerId)
            ->active()
            ->with('plan')
            ->orderByDesc('expires_at')
            ->get();
    }

    /**
     * Farms bought: the sum of every live subscription's allowance.
     *
     * Each row carries the number it was SOLD with, so editing a package later
     * cannot take a farm away from someone already using it.
     */
    public function purchasedFarmLimit(int $farmerId): int
    {
        return (int) FarmSubscription::where('farmer_id', $farmerId)
            ->active()
            ->sum('farm_limit');
    }

    /**
     * How many farms this farmer may own in total.
     *
     * Free allowance PLUS everything bought. Additive by decision: subscribing
     * must never leave a farmer with fewer slots than they had for nothing, and
     * a second package must always be worth buying.
     */
    public function farmAllowance(int $farmerId): int
    {
        return $this->freeLimit() + $this->purchasedFarmLimit($farmerId);
    }

    /** Slots left before another farm needs a package. Never negative. */
    public function remainingFarms(int $farmerId): int
    {
        return max(0, $this->farmAllowance($farmerId) - $this->ownedFarmCount($farmerId));
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
     * One question now: are they under their allowance. A live subscription no
     * longer means "as many as you like" — it means the farms that package was
     * sold with, added to the free allowance and to any other package they
     * hold.
     */
    public function canCreateFarm(int $farmerId): bool
    {
        return $this->ownedFarmCount($farmerId) < $this->farmAllowance($farmerId);
    }

    /**
     * Why a farmer was refused, in words meant for them.
     *
     * Distinguishes "you never had a plan" from "yours ran out", because the
     * second needs a date and reads as an accusation without one.
     */
    public function refusalMessage(int $farmerId): string
    {
        $allowance = $this->farmAllowance($farmerId);
        $owned     = $this->ownedFarmCount($farmerId);
        $bought    = $this->purchasedFarmLimit($farmerId);
        $latest    = $this->latestFor($farmerId);

        $held = "You have {$owned} of {$allowance} "
              . ($allowance === 1 ? 'farm' : 'farms') . '.';

        // Already paying: another package is what adds farms, not a renewal —
        // renewing only keeps the ones they have.
        if ($bought > 0) {
            return "{$held} Add another package to create more farms. "
                 . 'Your existing farms are unaffected.';
        }

        // Paid before and let it lapse. The date matters: "your plan ended on
        // the 3rd" is far more use than "you need a plan".
        if ($latest && $latest->is_expired) {
            return "{$held} Your package ended on {$latest->expires_at->format('d M Y')}. "
                 . 'Renew it to add more farms. Your existing farms are unaffected.';
        }

        return "{$held} Subscribe to add more.";
    }

    /**
     * Farm ids this farmer may still CHANGE, oldest first up to their allowance.
     *
     * A lapsed package drops the allowance back to the free limit, so the
     * farms it paid for stop being editable rather than disappearing. Oldest
     * first because the free allowance is the farms they had before they ever
     * paid: those must keep working whatever happens to a subscription.
     */
    public function writableFarmIds(int $farmerId): array
    {
        if (array_key_exists($farmerId, $this->writableCache)) {
            return $this->writableCache[$farmerId];
        }

        $allowance = $this->farmAllowance($farmerId);

        $ids = $allowance < 1
            ? []
            : Farm::where('farmer_id', $farmerId)
                ->orderBy('id')
                ->limit($allowance)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        return $this->writableCache[$farmerId] = $ids;
    }

    /** The farms that have gone read-only, for the app to mark. */
    public function lockedFarmIds(int $farmerId): array
    {
        $writable = $this->writableFarmIds($farmerId);

        return Farm::where('farmer_id', $farmerId)
            ->when($writable !== [], fn ($q) => $q->whereNotIn('id', $writable))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Is this farm read-only because its OWNER's package lapsed?
     *
     * Judged against the owner, not the caller: a manager working a farm they
     * were given is bound by the allowance of the person who owns it.
     */
    public function isFarmLocked(Farm $farm): bool
    {
        return !in_array(
            (int) $farm->id,
            $this->writableFarmIds((int) $farm->farmer_id),
            true
        );
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

        // From the catalogue admin writes, not a config file. Retired packages
        // are left out: they still have to read correctly on subscriptions
        // already sold, but nobody should be offered one.
        foreach (SubscriptionPlan::active()->ordered()->get() as $plan) {
            $amount = (float) $plan->amount;
            $months = (int) $plan->months;
            $key    = $plan->key;

            $plans[] = [
                'id'              => $plan->id,
                'farm_limit'      => (int) $plan->farm_limit,
                'key'             => $key,
                'label'           => $plan->label,
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
        $owned     = $this->ownedFarmCount($farmerId);
        $free      = $this->freeLimit();
        $bought    = $this->purchasedFarmLimit($farmerId);
        $allowance = $free + $bought;
        $canCreate = $owned < $allowance;

        $activePlans  = $this->activePlansFor($farmerId);
        $subscription = $activePlans->first() ?: $this->latestFor($farmerId);

        return [
            'owned_farms'    => $owned,
            'free_limit'     => $free,
            // What packages add on top of the free allowance, and the total.
            'purchased_farms' => $bought,
            'farm_allowance' => $allowance,
            'farms_remaining' => max(0, $allowance - $owned),

            // Kept for older app builds, which read these two names.
            'free_remaining' => max(0, $free - $owned),

            'can_create_farm' => $canCreate,

            // True whenever a PACKAGE is what stands between them and another
            // farm — including a farmer who already has one and needs a second,
            // which is the common case now that a package grants a fixed
            // number rather than unlimited.
            'needs_subscription' => !$canCreate,

            'message' => $canCreate ? null : $this->refusalMessage($farmerId),

            // The newest live package, for the "expires on" line.
            'subscription' => $subscription ? $this->present($subscription) : null,

            // EVERY live package. A farmer holding three needs to see all
            // three, since each expires on its own date.
            'active_subscriptions' => $activePlans
                ->map(fn (FarmSubscription $s) => $this->present($s))
                ->values()
                ->all(),

            // Which of their farms have gone read-only, so the app can
            // mark them without asking per farm.
            'locked_farm_ids' => $this->lockedFarmIds($farmerId),

            'plans' => $this->plans(),
        ];
    }

    /** One subscription as the API exposes it. */
    public function present(FarmSubscription $subscription): array
    {
        return [
            'id'              => $subscription->id,
            'plan_key'        => $subscription->plan_key,
            'plan_label'      => $subscription->plan_label,
            // What THIS sale granted, not what the package says today.
            'farm_limit'      => (int) $subscription->farm_limit,
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
        ?int $createdBy = null,
        ?string $expiresAt = null
    ): FarmSubscription {
        $plan = SubscriptionPlan::where('key', $planKey)->first();

        if (!$plan) {
            throw new \InvalidArgumentException("Unknown subscription plan [{$planKey}].");
        }

        $months = max(1, (int) $plan->months);

        // Where the term begins.
        //
        // TODAY unless the admin says otherwise.
        //
        // It used to chain behind any running term, which is right for a
        // renewal and wrong for everything else: a farmer who has used their
        // one farm and buys a second package wants the farm NOW, and chaining
        // gave them a package that granted nothing until the first one lapsed.
        // Packages stack — each contributes its farms while it is live — so a
        // purchase made today starts today.
        //
        // Renewals still chain: the renew form prefills From with the day after
        // the current term ends, and an explicit date always wins.
        $start = $startsAt
            ? Carbon::parse($startsAt)->startOfDay()
            : Carbon::today();

        // And where it ends.
        //
        // The admin picks this too, because a package sold on paper rarely runs
        // exactly to the arithmetic — a farmer given a few extra days, or a
        // term agreed to end with the season. Left blank it follows the plan's
        // months.
        //
        // addMonths then subDay: a one-month term starting on the 1st runs to
        // the 30th/31st inclusive, not into the next month's 1st.
        $end = $expiresAt
            ? Carbon::parse($expiresAt)->startOfDay()
            : $start->copy()->addMonths($months)->subDay();

        // A term that ends before it starts is a slip. Swapped rather than
        // stored, which would leave a subscription that is never live and no
        // hint as to why.
        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        return FarmSubscription::create([
            'farmer_id'  => $farmerId,
            'plan_id'    => $plan->id,
            'plan_key'   => $plan->key,
            'plan_label' => $plan->label,
            // SNAPSHOT: what this sale grants, frozen now. Editing the package
            // later must not change what somebody already bought.
            'farm_limit' => (int) $plan->farm_limit,
            'amount'     => $plan->amount,
            'months'     => $months,
            'starts_at'  => $start->toDateString(),
            'expires_at' => $end->toDateString(),
            'notes'      => $notes,
            'created_by' => $createdBy,
        ]);
    }
}
