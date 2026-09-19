<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\Farmer;
use App\Models\Tank;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Records who changed what on a farm.
 *
 * Called explicitly at each write rather than driven by model events. Two
 * reasons, both learned the hard way:
 *
 *  - A model observer fires on every row. Generating a tank's back-history
 *    inserts one row per meal per day — thousands for a single action — and
 *    the log would drown in them while saying nothing a farmer could read.
 *  - The useful sentence needs BOTH the old and the new value, and the intent
 *    behind the change. "store: 900 → 1200" is data; "Raised the feed store
 *    from 900 to 1200 kg" is the answer to the question being asked. Only the
 *    caller knows which it was.
 *
 * Every method here is best-effort: logging is a side effect, and a failure to
 * record what happened must never undo the thing that happened. Each call is
 * wrapped, and a failure is logged to the application log instead.
 */
class FarmActivityLogger
{
    /**
     * Write one entry.
     *
     * The actor is resolved from whoever is authenticated unless one is given
     * — the app authenticates a Farmer through Sanctum and the admin panel a
     * User through the session, and both reach the same write paths.
     */
    public function record(
        int $farmId,
        string $category,
        string $action,
        string $description,
        ?Tank $tank = null,
        array $changes = [],
        $actor = null
    ): void {
        try {
            $resolved = $this->resolveActor($actor, $farmId);

            FarmActivity::create([
                'farm_id'      => $farmId,
                'tank_id'      => $tank?->id,
                // Copied, not joined: tanks are hard-deleted and the entry
                // recording the deletion has to still name what went.
                'tank_name'    => $tank?->tank_name,
                'category'     => $category,
                'action'       => $action,
                // Trimmed to the column rather than throwing: an over-long
                // sentence is a cosmetic problem, a failed insert is a lost
                // record of a real change.
                'description'  => mb_substr($description, 0, 500),
                'changes'      => $changes ?: null,
                'actor_type'   => $resolved['type'],
                'actor_id'     => $resolved['id'],
                'actor_name'   => $resolved['name'],
                'actor_mobile' => $resolved['mobile'],
                'actor_role'   => $resolved['role'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Farm activity could not be recorded', [
                'farm_id'     => $farmId,
                'category'    => $category,
                'action'      => $action,
                'description' => $description,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Name, mobile and standing for whoever is acting.
     *
     * The farmer's standing is looked up per farm because it is the whole
     * point of the entry: "Partner" beside a change means something different
     * from "Manager" beside the same change.
     */
    private function resolveActor($actor, int $farmId): array
    {
        $actor ??= Auth::guard('sanctum')->user() ?? Auth::user();

        if ($actor instanceof Farmer) {
            return [
                'type'   => 'farmer',
                'id'     => $actor->id,
                'name'   => trim($actor->first_name . ' ' . $actor->last_name) ?: 'Farmer',
                'mobile' => $actor->mobile,
                'role'   => $this->farmRoleFor($actor->id, $farmId),
            ];
        }

        // The admin panel's User. `name` and `phone`, not first/last + mobile.
        if ($actor && isset($actor->name)) {
            return [
                'type'   => 'admin',
                'id'     => $actor->id ?? null,
                'name'   => $actor->name,
                'mobile' => $actor->phone ?? null,
                'role'   => 'admin',
            ];
        }

        // A console command or a queued job. Named rather than left blank, so
        // an entry nobody recognises still says where it came from.
        return [
            'type'   => 'system',
            'id'     => null,
            'name'   => 'System',
            'mobile' => null,
            'role'   => 'system',
        ];
    }

    /** Owner, partner or manager on this particular farm. */
    private function farmRoleFor(int $farmerId, int $farmId): string
    {
        $farm = Farm::withTrashed()->find($farmId);

        if ($farm && (int) $farm->farmer_id === $farmerId) {
            return 'owner';
        }

        return app(FarmAccessService::class)
            ->permissionFor($farmerId, $farm)
            ->role;
    }

    // ── Shorthands ──────────────────────────────────────────────────────────
    //
    // Named for what happened rather than for the table that changed, so a
    // call site reads as the thing it is doing.

    public function farmCreated(Farm $farm): void
    {
        $this->record(
            $farm->id,
            FarmActivity::CATEGORY_FARM,
            FarmActivity::ACTION_CREATED,
            "Created the farm \"{$farm->farm_name}\"."
        );
    }

    public function farmUpdated(Farm $farm, array $changes): void
    {
        if (empty($changes)) {
            return;
        }

        $this->record(
            $farm->id,
            FarmActivity::CATEGORY_FARM,
            FarmActivity::ACTION_UPDATED,
            'Updated the farm: ' . $this->summarise($changes) . '.',
            null,
            $changes
        );
    }

    public function farmDeleted(Farm $farm): void
    {
        $this->record(
            $farm->id,
            FarmActivity::CATEGORY_FARM,
            FarmActivity::ACTION_DELETED,
            "Deleted the farm \"{$farm->farm_name}\"."
        );
    }

    public function tankAdded(Farm $farm, Tank $tank): void
    {
        $when = $tank->stocking_date
            ? ', stocked ' . $this->date($tank->stocking_date)
            : '';

        $this->record(
            $farm->id,
            FarmActivity::CATEGORY_TANK,
            FarmActivity::ACTION_CREATED,
            "Added {$tank->tank_name}{$when}.",
            $tank
        );
    }

    public function tankUpdated(Tank $tank, array $changes): void
    {
        if (empty($changes) || !$tank->farm_id) {
            return;
        }

        $this->record(
            $tank->farm_id,
            FarmActivity::CATEGORY_TANK,
            FarmActivity::ACTION_UPDATED,
            "Updated {$tank->tank_name}: " . $this->summarise($changes) . '.',
            $tank,
            $changes
        );
    }

    public function tankDeleted(Tank $tank): void
    {
        if (!$tank->farm_id) {
            return;
        }

        $this->record(
            $tank->farm_id,
            FarmActivity::CATEGORY_TANK,
            FarmActivity::ACTION_DELETED,
            "Deleted {$tank->tank_name} and its feed records.",
            $tank
        );
    }

    /** A crop started. */
    public function tankActivated(Tank $tank, ?string $stockingDate, float $usedBefore = 0): void
    {
        if (!$tank->farm_id) {
            return;
        }

        $sentence = "Started a new crop in {$tank->tank_name}";

        if ($stockingDate) {
            $sentence .= ', stocked ' . $this->date($stockingDate);
        }

        if ($usedBefore > 0) {
            $sentence .= ', with ' . $this->kg($usedBefore) . ' already fed';
        }

        $this->record(
            $tank->farm_id,
            FarmActivity::CATEGORY_TANK,
            FarmActivity::ACTION_ACTIVATED,
            $sentence . '.',
            $tank
        );
    }

    /** A crop finished. */
    public function tankHarvested(Tank $tank, ?float $harvestQuantity = null): void
    {
        if (!$tank->farm_id) {
            return;
        }

        $sentence = "Harvested {$tank->tank_name}";

        if ($harvestQuantity !== null) {
            $sentence .= ', ' . $this->kg($harvestQuantity) . ' harvested';
        }

        $this->record(
            $tank->farm_id,
            FarmActivity::CATEGORY_TANK,
            FarmActivity::ACTION_HARVESTED,
            $sentence . '.',
            $tank
        );
    }

    public function feedRecorded(Tank $tank, string $date, float $meals, float $quantity): void
    {
        if (!$tank->farm_id) {
            return;
        }

        $this->record(
            $tank->farm_id,
            FarmActivity::CATEGORY_FEED,
            FarmActivity::ACTION_CREATED,
            sprintf(
                'Recorded meal %s of %s for %s on %s.',
                $this->number($meals),
                $this->kg($quantity),
                $tank->tank_name,
                $this->date($date)
            ),
            $tank,
            ['feed_date' => $date, 'meals' => $meals, 'feed_quantity' => $quantity]
        );
    }

    public function feedUpdated(Tank $tank, string $date, array $changes): void
    {
        if (!$tank->farm_id) {
            return;
        }

        $this->record(
            $tank->farm_id,
            FarmActivity::CATEGORY_FEED,
            FarmActivity::ACTION_UPDATED,
            sprintf(
                'Corrected %s\'s feed on %s: %s.',
                $tank->tank_name,
                $this->date($date),
                $this->summarise($changes)
            ),
            $tank,
            $changes + ['feed_date' => $date]
        );
    }

    public function feedDeleted(Tank $tank, string $date, float $quantity): void
    {
        if (!$tank->farm_id) {
            return;
        }

        $this->record(
            $tank->farm_id,
            FarmActivity::CATEGORY_FEED,
            FarmActivity::ACTION_DELETED,
            sprintf(
                'Deleted a %s feed entry for %s on %s.',
                $this->kg($quantity),
                $tank->tank_name,
                $this->date($date)
            ),
            $tank,
            ['feed_date' => $date, 'feed_quantity' => $quantity]
        );
    }

    public function storeUpdated(Farm $farm, array $changes): void
    {
        if (empty($changes)) {
            return;
        }

        $this->record(
            $farm->id,
            FarmActivity::CATEGORY_STORE,
            FarmActivity::ACTION_UPDATED,
            'Updated the feed store: ' . $this->summarise($changes) . '.',
            null,
            $changes
        );
    }

    public function accessGranted(Farm $farm, string $personName, ?string $mobile, string $role, array $abilities): void
    {
        $who = $mobile ? "{$personName} ({$mobile})" : $personName;
        $can = $abilities ? ' — ' . implode(', ', $abilities) : '';

        $this->record(
            $farm->id,
            FarmActivity::CATEGORY_ACCESS,
            FarmActivity::ACTION_GRANTED,
            "Gave {$who} {$role} access{$can}.",
            null,
            ['person' => $personName, 'mobile' => $mobile, 'role' => $role, 'abilities' => $abilities]
        );
    }

    public function accessRevoked(Farm $farm, string $personName, ?string $mobile): void
    {
        $who = $mobile ? "{$personName} ({$mobile})" : $personName;

        $this->record(
            $farm->id,
            FarmActivity::CATEGORY_ACCESS,
            FarmActivity::ACTION_REVOKED,
            "Removed {$who}'s access.",
            null,
            ['person' => $personName, 'mobile' => $mobile]
        );
    }

    // ── Formatting ──────────────────────────────────────────────────────────

    /**
     * Turn a field map into readable prose.
     *
     * Shaped `['Store' => ['900', '1200']]` for a before/after, or
     * `['Store' => '1200']` when only the new value is known.
     */
    private function summarise(array $changes): string
    {
        $parts = [];

        foreach ($changes as $label => $value) {
            if (is_array($value) && count($value) === 2) {
                [$from, $to] = array_values($value);
                $parts[] = sprintf(
                    '%s %s → %s',
                    $label,
                    $this->blankAsDash($from),
                    $this->blankAsDash($to)
                );
                continue;
            }

            $parts[] = $label . ' ' . $this->blankAsDash(
                is_array($value) ? implode(', ', $value) : $value
            );
        }

        return implode(', ', $parts);
    }

    /** An empty value reads as "not set", not as an empty gap in a sentence. */
    private function blankAsDash($value): string
    {
        $value = is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;

        return trim($value) === '' ? 'not set' : $value;
    }

    /** Trailing zeros dropped: "2 kg", not "2.00 kg". */
    private function kg(float $value): string
    {
        return $this->number($value) . ' kg';
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function date($value): string
    {
        try {
            return \Carbon\Carbon::parse($value)->format('d M Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}
