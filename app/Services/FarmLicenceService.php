<?php

namespace App\Services;

use App\Models\AppConfig;
use App\Models\Farm;
use App\Models\FarmSubscription;
use Carbon\Carbon;

/**
 * What covers each farm, and what happens when nothing does.
 *
 * A farm is licensed INDIVIDUALLY, not out of a shared allowance. It is either
 * inside its free period or attached to a live package, and when neither holds
 * it is LOCKED: still visible, still carrying every record ever written to it,
 * but closed to new data until somebody renews it.
 *
 * That is the whole point of the design. A one-month, one-farm package covers
 * that farm for that month; renewing brings back that farm and no other. A
 * farmer with three farms and one package has one working farm and two locked
 * ones, which is what was sold.
 *
 * Both free settings are admin's to choose:
 *   farm_free_count   how many farms come without paying (0 is allowed)
 *   farm_free_months  how long they last; 0 means never. Defaults to 3.
 */
class FarmLicenceService
{
    /** How many farms a farmer gets without paying. */
    public function freeCount(): int
    {
        return max(0, (int) AppConfig::getValue('farm_free_count', 2));
    }

    /** How long a free farm lasts, in months. 0 = never expires. */
    public function freeMonths(): int
    {
        return max(0, (int) AppConfig::getValue('farm_free_months', 3));
    }

    /**
     * Free slots this farmer has spent.
     *
     * Counts farms RECORDED as having taken a free slot, not farms that happen
     * to have no subscription today. Two things would otherwise give the slot
     * back and let a farmer cycle for ever:
     *
     *   letting the free period lapse — the farm is still there, still theirs
     *   buying a package for that farm — it is paid now, so nothing reads as
     *   "on free", and the next farm they create takes the phantom free slot
     *   instead of the package they just paid for
     *
     * A free slot is spent once and stays spent. Soft-deleted farms are
     * included for the same reason: deleting a farm hides it, and its records
     * survive, so the slot has not come back.
     */
    public function freeFarmsUsed(int $farmerId, ?int $exceptFarmId = null): int
    {
        return Farm::withTrashed()
            ->where('farmer_id', $farmerId)
            ->where('took_free_slot', true)
            ->when($exceptFarmId, fn ($q) => $q->where('id', '!=', $exceptFarmId))
            ->count();
    }

    /**
     * [$exceptFarmId] leaves one farm out of the count.
     *
     * Needed because cover is attached AFTER the row exists, so the farm being
     * covered would otherwise count against its own slot — and the very first
     * free farm on a one-free-farm plan would be told there was no room for it.
     */
    public function freeSlotsLeft(int $farmerId, ?int $exceptFarmId = null): int
    {
        return max(0, $this->freeCount() - $this->freeFarmsUsed($farmerId, $exceptFarmId));
    }

    /**
     * Live packages this farmer holds that still have room for a farm.
     *
     * A package covering three farms can be attached to three; the fourth needs
     * another package.
     */
    public function subscriptionsWithRoom(int $farmerId)
    {
        return FarmSubscription::where('farmer_id', $farmerId)
            ->active()
            ->withCount('coveredFarms')
            ->get()
            ->filter(fn ($s) => $s->covered_farms_count < (int) $s->farm_limit)
            ->values();
    }

    /** May this farmer start another farm at all? */
    public function canCreateFarm(int $farmerId): bool
    {
        return $this->freeSlotsLeft($farmerId) > 0
            || $this->subscriptionsWithRoom($farmerId)->isNotEmpty();
    }

    /**
     * Attach cover to a farm that has just been created.
     *
     * Free slots are spent FIRST — a farmer should not burn a package they paid
     * for while a free slot sits unused.
     */
    public function coverNewFarm(Farm $farm): Farm
    {
        // Excluding this farm: it already exists, and without that it occupies
        // the very slot being asked about.
        if ($this->freeSlotsLeft((int) $farm->farmer_id, (int) $farm->id) > 0) {
            $months = $this->freeMonths();

            $farm->forceFill([
                'covered_by_subscription_id' => null,
                // NULL when the free plan has no expiry.
                'free_until' => $months > 0
                    ? Carbon::today()->addMonths($months)->toDateString()
                    : null,
                // Written down, so the slot stays spent even after this farm
                // is later moved onto a paid package. See [freeFarmsUsed].
                'took_free_slot' => true,
            ])->save();

            return $farm;
        }

        $package = $this->subscriptionsWithRoom((int) $farm->farmer_id)->first();

        if ($package) {
            $farm->forceFill([
                'covered_by_subscription_id' => $package->id,
                'free_until'                 => null,
            ])->save();
        }

        return $farm;
    }

    /**
     * Put one farm under one package.
     *
     * What admin does when a farmer rings about a locked farm: take the payment,
     * record the package, point it at the farm they are asking about.
     */
    public function attach(Farm $farm, FarmSubscription $subscription): void
    {
        $farm->forceFill([
            'covered_by_subscription_id' => $subscription->id,
            // The free period is spent the moment a package takes over; leaving
            // a date behind would quietly un-lock the farm when the package
            // later lapsed.
            'free_until' => null,
            // Likewise the grandfathering. Once a package has been sold for
            // this farm it is on the paid plan, and the farmer expects a
            // renewal notice rather than silent immunity.
            'legacy_free' => false,
            // `took_free_slot` is deliberately NOT cleared. The slot was
            // spent when this farm was created; paying for the farm afterwards
            // does not earn the farmer a fresh free one.
        ])->save();
    }

    /**
     * Is this farm closed to new data?
     *
     * Derived, never stored. A stored flag needs something to run in order to
     * stay true, and the day that something stops running every lapsed farm
     * silently keeps working.
     */
    public function isLocked(Farm $farm): bool
    {
        if ($farm->covered_by_subscription_id) {
            $sub = $farm->relationLoaded('cover')
                ? $farm->cover
                : FarmSubscription::find($farm->covered_by_subscription_id);

            return !($sub && $sub->is_active);
        }

        // Created under the old unlimited free plan. Never locks — see the
        // migration that added the flag.
        if ($farm->legacy_free) {
            return false;
        }

        // A free period with an end date: locked once that date has passed.
        if ($farm->free_until !== null) {
            return Carbon::parse($farm->free_until)->startOfDay()->lessThan(Carbon::today());
        }

        // No end date, and that means one of two opposite things:
        //
        //   took_free_slot = 1  the farm WAS given a free slot, under a free
        //                       plan set to "Never expires". Open, for ever.
        //   took_free_slot = 0  nothing ever covered it — an admin ticking
        //                       "Create anyway" past the farmer's allowance.
        //                       Locked until a package is pointed at it.
        //
        // Reading only `free_until` cannot tell these apart, and treating NULL
        // as "never covered" locked every farm created while the free plan had
        // no expiry — which is the default setting.
        return !$farm->took_free_slot;
    }

    /** Why it is locked, in words meant for the farmer. */
    public function lockReason(Farm $farm): ?string
    {
        if (!$this->isLocked($farm)) {
            return null;
        }

        if ($farm->covered_by_subscription_id) {
            $sub = FarmSubscription::find($farm->covered_by_subscription_id);

            $on = $sub?->expires_at ? $sub->expires_at->format('d M Y') : null;

            return $on
                ? "This farm's package ended on {$on}. Renew it to record data again. "
                  . 'Everything already recorded is safe.'
                : 'This farm has no live package. Renew it to record data again.';
        }

        $on = $farm->free_until
            ? Carbon::parse($farm->free_until)->format('d M Y')
            : null;

        return $on
            ? "This farm's free period ended on {$on}. Take a package to record data again. "
              . 'Everything already recorded is safe.'
            : 'This farm needs a package before more data can be recorded.';
    }

    /**
     * When this farm's cover runs out, or null when it does not.
     *
     * Used for the "expires in N days" notice on both sides.
     */
    public function coverEndsOn(Farm $farm): ?Carbon
    {
        if ($farm->covered_by_subscription_id) {
            $sub = FarmSubscription::find($farm->covered_by_subscription_id);

            return $sub?->expires_at ? $sub->expires_at->copy()->startOfDay() : null;
        }

        return $farm->free_until ? Carbon::parse($farm->free_until)->startOfDay() : null;
    }

    /** Everything a screen needs to describe one farm's standing. */
    public function statusFor(Farm $farm): array
    {
        $ends = $this->coverEndsOn($farm);

        return [
            'is_locked'   => $this->isLocked($farm),
            'lock_reason' => $this->lockReason($farm),
            'is_free'     => $farm->covered_by_subscription_id === null,
            'cover_ends_on'   => $ends?->toDateString(),
            'days_remaining'  => $ends ? Carbon::today()->diffInDays($ends, false) : null,
            // True for a farm that genuinely has no end date: grandfathered,
            // or holding a free slot while the free plan has no expiry. NOT
            // true for one that simply never got cover — that is locked, not
            // eternal.
            'never_expires'   => $ends === null
                && ((bool) $farm->legacy_free || (bool) $farm->took_free_slot),
        ];
    }
}
