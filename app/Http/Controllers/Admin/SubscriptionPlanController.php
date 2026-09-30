<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The packages admin sells: so many farms, for so many months, at a price.
 *
 * Written here rather than in a config file, so "2 months, 4 farms" is a form
 * rather than a deploy.
 */
class SubscriptionPlanController extends Controller
{
    public function index()
    {
        return view('admin.subscription-plans.index', [
            'plans' => SubscriptionPlan::withCount([
                'subscriptions',
                'subscriptions as active_subscriptions_count' => fn ($q) => $q->active(),
            ])->ordered()->get(),
        ]);
    }

    public function create()
    {
        return view('admin.subscription-plans.create', [
            'plan'     => new SubscriptionPlan(['months' => 1, 'farm_limit' => 1, 'is_active' => true]),
            'currency' => config('subscriptions.currency_symbol', '₹'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // The key identifies the package on every subscription ever sold from
        // it, so it is generated once and never asked for again — an admin
        // typing one would eventually reuse a retired package's key and merge
        // two products' histories.
        $data['key'] = $this->uniqueKey($data['label'], $data['months'], $data['farm_limit']);

        SubscriptionPlan::create($data);

        return redirect()->route('subscription-plans.index')
            ->with('success', "Package \"{$data['label']}\" created.");
    }

    public function edit(SubscriptionPlan $subscription_plan)
    {
        return view('admin.subscription-plans.edit', [
            'plan'     => $subscription_plan,
            'currency' => config('subscriptions.currency_symbol', '₹'),
            'sold'     => $subscription_plan->subscriptions()->count(),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscription_plan)
    {
        // `key` is deliberately not updatable — see store().
        $subscription_plan->update($this->validated($request));

        return redirect()->route('subscription-plans.index')
            ->with('success', "Package \"{$subscription_plan->label}\" updated. "
                . 'Subscriptions already sold keep the terms they were sold under.');
    }

    /**
     * Retire or bring back a package.
     *
     * Never deleted: subscriptions already sold point at it, and their history
     * has to keep reading correctly. Retired simply means "no longer offered".
     */
    public function toggle(SubscriptionPlan $subscription_plan)
    {
        $subscription_plan->update(['is_active' => !$subscription_plan->is_active]);

        return back()->with(
            'success',
            $subscription_plan->is_active
                ? "\"{$subscription_plan->label}\" is on sale again."
                : "\"{$subscription_plan->label}\" retired. Subscriptions already sold are unaffected."
        );
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label'      => ['required', 'string', 'max:80'],
            'months'     => ['required', 'integer', 'min:1', 'max:120'],
            'farm_limit' => ['required', 'integer', 'min:1', 'max:500'],
            'amount'     => ['required', 'numeric', 'min:0', 'max:9999999'],
            // Days before expiry to warn, typed as "30,15,7". Optional — the
            // model falls back to a sensible set for the plan's length.
            'reminders'  => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['nullable', 'boolean'],
        ], [
            'farm_limit.required' => 'Say how many farms this package allows.',
            'months.required'     => 'Say how many months this package runs for.',
        ]);

        // "30, 15, 7" → [30, 15, 7]. Rubbish and duplicates are dropped rather
        // than stored, and an empty result becomes null so the model's own
        // defaults take over.
        $days = array_values(array_unique(array_filter(
            array_map('intval', preg_split('/[^0-9]+/', (string) ($data['reminders'] ?? ''))),
            fn ($d) => $d > 0
        )));
        rsort($days);

        $data['reminders']  = $days ?: null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? $data['months']);
        $data['is_active']  = $request->boolean('is_active');

        return $data;
    }

    private function uniqueKey(string $label, int $months, int $farms): string
    {
        $base = Str::slug("{$months}m-{$farms}f-" . $label, '_');
        $key  = $base;
        $n    = 2;

        while (SubscriptionPlan::where('key', $key)->exists()) {
            $key = $base . '_' . $n++;
        }

        return $key;
    }
}
