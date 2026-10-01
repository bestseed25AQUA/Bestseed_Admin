<?php

namespace App\Observers;

use App\Models\Farm;
use App\Services\SubscriptionService;

/**
 * Charge each new farm to the package that allowed it.
 *
 * On the model rather than in the controller on purpose: farms are created
 * from the API, from seeders and from the admin panel, and a package's grant
 * has to be spent wherever that happens or the same slot is sold twice.
 */
class FarmObserver
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function creating(Farm $farm): void
    {
        // Already attributed by whoever is creating it — a restore, a test, a
        // data fix. Do not second-guess it.
        if ($farm->subscription_id !== null) {
            return;
        }

        if (!$farm->farmer_id) {
            return;
        }

        $farm->subscription_id = $this->subscriptions->slotForNewFarm((int) $farm->farmer_id);
    }
}
