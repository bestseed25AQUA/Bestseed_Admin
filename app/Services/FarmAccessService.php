<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\FarmAccessMember;
use App\Support\FarmPermission;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for "who may touch which farm".
 *
 * Both the listing endpoint and the route middleware go through here, so the
 * rule that decides what a farmer sees is the same rule that decides what they
 * may change — they cannot drift apart.
 */
class FarmAccessService
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /**
     * Resolve one farmer's standing on one farm.
     *
     * Owner wins outright. Otherwise we look for a grant this farmer redeemed
     * that is still live (redeemed, not revoked, not expired); the newest one
     * wins so that re-scanning a fresh QR upgrades stale permissions.
     */
    public function permissionFor(?int $farmerId, ?Farm $farm): FarmPermission
    {
        if ($farmerId === null || $farm === null) {
            return FarmPermission::none();
        }

        $locked = $this->subscriptions->isFarmLocked($farm);

        if ((int) $farm->farmer_id === (int) $farmerId) {
            return $locked ? FarmPermission::owner()->readOnly() : FarmPermission::owner();
        }

        $member = FarmAccessMember::query()
            ->where('farm_id', $farm->id)
            ->forFarmer($farmerId)
            ->live()
            ->latest('id')
            ->first();

        $permission = $member ? FarmPermission::fromMember($member) : FarmPermission::none();

        // A shared farm follows its OWNER's allowance: the manager did not
        // buy the package and cannot be judged against their own.
        return $locked && !$permission->isDenied() ? $permission->readOnly() : $permission;
    }

    /**
     * Every farm this farmer may see: the ones they own, plus the ones they
     * hold a live grant on that actually carries view access.
     */
    public function visibleFarmsQuery(int $farmerId): Builder
    {
        return Farm::query()->accessibleBy($farmerId);
    }

    /**
     * Permissions for a set of farms in one query instead of one per farm,
     * keyed by farm id. Used when decorating a farm list.
     *
     * @param  \Illuminate\Support\Collection<int, Farm>  $farms
     * @return array<int, FarmPermission>
     */
    public function permissionsForMany(int $farmerId, $farms): array
    {
        $farms = collect($farms);

        $notOwned = $farms->filter(fn (Farm $farm) => (int) $farm->farmer_id !== (int) $farmerId);

        // One query for every grant that matters, newest last so that writing
        // them into the map leaves the newest grant per farm in place.
        $members = $notOwned->isEmpty()
            ? collect()
            : FarmAccessMember::query()
                ->whereIn('farm_id', $notOwned->pluck('id'))
                ->forFarmer($farmerId)
                ->live()
                ->orderBy('id')
                ->get()
                ->keyBy('farm_id');

        $permissions = [];

        foreach ($farms as $farm) {
            $locked = $this->subscriptions->isFarmLocked($farm);

            if ((int) $farm->farmer_id === (int) $farmerId) {
                $permissions[$farm->id] = $locked
                    ? FarmPermission::owner()->readOnly()
                    : FarmPermission::owner();
                continue;
            }

            $member = $members->get($farm->id);
            $permission = $member
                ? FarmPermission::fromMember($member)
                : FarmPermission::none();

            $permissions[$farm->id] = $locked && !$permission->isDenied()
                ? $permission->readOnly()
                : $permission;
        }

        return $permissions;
    }
}
