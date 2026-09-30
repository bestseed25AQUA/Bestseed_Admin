<?php

namespace Database\Seeders;

use App\Models\Farmer;
use App\Models\FarmSubscription;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Demo subscriptions covering every state the admin list can show.
 *
 * Run: php artisan db:seed --class=DemoSubscriptionSeeder
 * Undo: php artisan db:seed --class=DemoSubscriptionSeeder --force  (re-runs clean)
 *       or DemoSubscriptionSeeder::purge() from tinker.
 */
class DemoSubscriptionSeeder extends Seeder
{
    /** Demo farmers get mobiles in this block so they are easy to find. */
    private const MOBILE_PREFIX = '90000000';

    public function run(): void
    {
        self::purge();

        $plans = SubscriptionPlan::all()->keyBy('key');

        if ($plans->isEmpty()) {
            $this->command?->warn('No subscription packages exist. Create some first.');
            return;
        }

        $monthly = $plans->get('month_1') ?? $plans->first();
        $quarter = $plans->get('month_3') ?? $plans->first();
        $half    = $plans->get('month_6') ?? $plans->first();
        $annual  = $plans->get('year_1')  ?? $plans->first();

        // [slot, name, plan, days until expiry, cancelled, note]
        $rows = [
            [1, 'Healthy Annual',   $annual,  240,  false, 'Bought in full. Nothing due.'],
            [2, 'Healthy Halfyear', $half,     95,  false, 'Comfortably inside its term.'],
            [3, 'Notice Only',      $monthly,  12,  false, 'Inside the 15-day notice, NOT amber — a 1 Month package warns from 7.'],
            [4, 'Warning Amber',    $monthly,   5,  false, 'Inside its own 7-day window. Amber row, and on the call list.'],
            [5, 'Last Day',         $quarter,   0,  false, 'Expires today. Still valid all of today.'],
            [6, 'Tomorrow',         $monthly,   1,  false, 'One day left.'],
            [7, 'Long Plan Amber',  $annual,   20,  false, 'A 1 Year package warns from 30, so this is amber at 20 days.'],
            [8, 'Lapsed Recently',  $monthly,  -3,  false, 'Expired three days ago. Red.'],
            [9, 'Lapsed Long Ago',  $quarter, -60,  false, 'Expired two months ago.'],
            [10, 'Cancelled',       $half,     45,  true,  'Refunded and cancelled while still in date.'],
        ];

        foreach ($rows as [$slot, $name, $plan, $daysLeft, $cancelled, $note]) {
            $farmer = $this->farmer($slot, $name);

            $expires = Carbon::today()->addDays($daysLeft);
            $starts  = $expires->copy()->subMonths(max(1, (int) $plan->months))->addDay();

            FarmSubscription::create([
                'farmer_id'    => $farmer->id,
                'plan_id'      => $plan->id,
                'plan_key'     => $plan->key,
                'plan_label'   => $plan->label,
                'farm_limit'   => (int) $plan->farm_limit,
                'amount'       => $plan->amount,
                'months'       => (int) $plan->months,
                'starts_at'    => $starts->toDateString(),
                'expires_at'   => $expires->toDateString(),
                'cancelled_at' => $cancelled ? now() : null,
                'notes'        => '[demo] ' . $note,
            ]);
        }

        // Someone holding two live packages at once, to show the allowance
        // adding up rather than replacing.
        $stacked = $this->farmer(11, 'Two Packages');

        foreach ([[$monthly, 20], [$quarter, 70]] as [$plan, $daysLeft]) {
            $expires = Carbon::today()->addDays($daysLeft);

            FarmSubscription::create([
                'farmer_id'  => $stacked->id,
                'plan_id'    => $plan->id,
                'plan_key'   => $plan->key,
                'plan_label' => $plan->label,
                'farm_limit' => (int) $plan->farm_limit,
                'amount'     => $plan->amount,
                'months'     => (int) $plan->months,
                'starts_at'  => $expires->copy()->subMonths(max(1, (int) $plan->months))->addDay()->toDateString(),
                'expires_at' => $expires->toDateString(),
                'notes'      => '[demo] Two live packages — allowance is the sum of both.',
            ]);
        }

        $this->command?->info('Demo subscriptions created for 11 farmers (mobiles 9000000001+).');
    }

    /** Remove everything this seeder made, leaving real data alone. */
    public static function purge(): void
    {
        $ids = Farmer::where('mobile', 'like', self::MOBILE_PREFIX . '%')->pluck('id');

        if ($ids->isNotEmpty()) {
            FarmSubscription::whereIn('farmer_id', $ids)->delete();
            Farmer::whereIn('id', $ids)->delete();
        }
    }

    private function farmer(int $slot, string $name): Farmer
    {
        return Farmer::create([
            'first_name' => $name,
            'last_name'  => '(demo)',
            'mobile'     => self::MOBILE_PREFIX . str_pad((string) $slot, 2, '0', STR_PAD_LEFT),
            'role'       => 'farmer',
        ]);
    }
}
