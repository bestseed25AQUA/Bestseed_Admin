<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmSubscription;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Recording Farm Management subscriptions taken over the phone.
 *
 * The farmer picks a plan in the app, rings the helpline, and pays the person
 * who answers. That person comes here, finds them by mobile number, picks the
 * plan they bought and saves. Nothing else grants the allowance, so this screen
 * is the whole billing system.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
        $this->middleware('permission:subscriptions.view')->only(['index', 'show']);
        $this->middleware('permission:subscriptions.create')->only(['create', 'store', 'lookupFarmer']);
        $this->middleware('permission:subscriptions.update')->only(['edit', 'update', 'cancel']);
        $this->middleware('permission:subscriptions.delete')->only(['destroy']);
    }

    /**
     * Every subscription, newest first, with the expiring ones surfaced.
     *
     * Sorted by expiry ascending when filtering to expiring, because the whole
     * point of that filter is "who do I ring first".
     */
    public function index(Request $request)
    {
        $filter = $request->input('state', 'all');
        $search = trim((string) $request->input('q', ''));

        $query = FarmSubscription::with('farmer');

        // Filters run in SQL rather than on the collection so the list stays
        // usable once there are thousands of rows.
        match ($filter) {
            'active'    => $query->active(),
            // Each plan's own window, so this list matches the amber rows in it.
            'expiring'  => $query->expiringSoon(),
            'expired'   => $query->expired(),
            'cancelled' => $query->cancelled(),
            default     => null,
        };

        if ($search !== '') {
            $digits = preg_replace('/\D/', '', $search);

            $query->whereHas('farmer', function ($q) use ($search, $digits) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");

                if ($digits !== '') {
                    $q->orWhere('mobile', 'like', "%{$digits}%");
                }
            });
        }

        $subscriptions = $query
            ->orderByRaw('cancelled_at IS NULL DESC')
            ->orderBy('expires_at', $filter === 'expiring' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'filter'        => $filter,
            'search'        => $search,
            'counts'        => $this->counts(),
        ]);
    }

    /**
     * The headline numbers, and the admin's half of "notify them".
     *
     * Computed from the dates on every request rather than stored, so they
     * cannot go stale when the scheduler is down — which is exactly when
     * somebody needs to notice that renewals are being missed.
     */
    private function counts(): array
    {
        return [
            'active'   => FarmSubscription::active()->count(),
            'expiring' => FarmSubscription::expiringSoon()->count(),
            'expired'  => FarmSubscription::expired()->count(),
        ];
    }

    public function create(Request $request)
    {
        return view('admin.subscriptions.create', [
            'plans'    => config('subscriptions.plans', []),
            'currency' => config('subscriptions.currency_symbol', '₹'),
            'farmer'   => $request->filled('farmer_id')
                ? Farmer::find($request->input('farmer_id'))
                : null,
        ]);
    }

    /**
     * GET /admin/subscriptions/lookup?mobile=...
     *
     * Find the caller by their number so the admin never types a farmer id.
     * Returns their current standing too, so the person on the phone can say
     * "you already have until the 12th" before taking money twice.
     */
    public function lookupFarmer(Request $request)
    {
        $digits = preg_replace('/\D/', '', (string) $request->input('mobile', ''));

        if (strlen($digits) < 10) {
            return response()->json([
                'status'  => false,
                'message' => 'Enter the full 10-digit mobile number.',
            ], 422);
        }

        $farmer = Farmer::where('mobile', $digits)
            ->first(['id', 'first_name', 'last_name', 'mobile']);

        if (!$farmer) {
            return response()->json([
                'status'  => false,
                'message' => 'No farmer is registered with this number.',
            ], 404);
        }

        $active = $this->subscriptions->activeFor($farmer->id);

        return response()->json([
            'status' => true,
            'farmer' => [
                'id'     => $farmer->id,
                'name'   => trim($farmer->first_name . ' ' . $farmer->last_name) ?: 'Unnamed farmer',
                'mobile' => $farmer->mobile,
                'farms'  => Farm::where('farmer_id', $farmer->id)->count(),
            ],
            'active' => $active ? [
                'plan_label'     => $active->plan_label,
                'expires_on'     => $active->expires_at->format('d M Y'),
                'days_remaining' => $active->days_remaining,
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'farmer_id' => ['required', 'integer', 'exists:farmers,id'],
            'plan_key'  => ['required', Rule::in(array_keys(config('subscriptions.plans', [])))],
            // Optional. Left blank the service starts it today, or the day
            // after an existing term ends so a renewal does not overlap.
            'starts_at' => ['nullable', 'date'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ], [
            'farmer_id.required' => 'Find the farmer by mobile number first.',
            'plan_key.required'  => 'Choose the package the farmer paid for.',
        ]);

        try {
            $subscription = $this->subscriptions->subscribe(
                farmerId:  (int) $validated['farmer_id'],
                planKey:   $validated['plan_key'],
                startsAt:  $validated['starts_at'] ?? null,
                notes:     $validated['notes'] ?? null,
                createdBy: auth()->id(),
            );
        } catch (\Throwable $e) {
            Log::error('Subscription could not be recorded', [
                'farmer_id' => $validated['farmer_id'],
                'plan_key'  => $validated['plan_key'],
                'error'     => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'The subscription could not be saved. Please try again.');
        }

        return redirect()
            ->route('subscriptions.index')
            ->with('success', "{$subscription->plan_label} recorded. Active until {$subscription->expires_at->format('d M Y')}.");
    }

    public function show(FarmSubscription $subscription)
    {
        $subscription->load('farmer', 'reminders');

        return view('admin.subscriptions.show', [
            'subscription' => $subscription,
            'farms'        => Farm::where('farmer_id', $subscription->farmer_id)->count(),
        ]);
    }

    public function edit(FarmSubscription $subscription)
    {
        return view('admin.subscriptions.edit', [
            'subscription' => $subscription->load('farmer'),
            'plans'        => config('subscriptions.plans', []),
            'currency'     => config('subscriptions.currency_symbol', '₹'),
        ]);
    }

    /**
     * Correct a recorded subscription.
     *
     * Dates are editable here, unlike on create, because the reason to edit is
     * almost always that they are wrong: the wrong plan was clicked, or the
     * term should have started when the farmer actually paid.
     */
    public function update(Request $request, FarmSubscription $subscription)
    {
        $validated = $request->validate([
            'plan_key'   => ['required', Rule::in(array_keys(config('subscriptions.plans', [])))],
            'starts_at'  => ['required', 'date'],
            'expires_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ], [
            'expires_at.after_or_equal' => 'The end date cannot be before the start date.',
        ]);

        $plan = config("subscriptions.plans.{$validated['plan_key']}");

        $subscription->update([
            'plan_key'   => $validated['plan_key'],
            'plan_label' => $plan['label'] ?? $validated['plan_key'],
            'amount'     => $plan['amount'] ?? 0,
            'months'     => $plan['months'] ?? 1,
            'starts_at'  => $validated['starts_at'],
            'expires_at' => $validated['expires_at'],
            'notes'      => $validated['notes'] ?? null,
        ]);

        // Reminders already sent describe the OLD dates. Pushing the expiry
        // back without clearing them means the farmer is never warned again,
        // because every threshold is already marked done.
        $subscription->reminders()->delete();

        return redirect()
            ->route('subscriptions.index')
            ->with('success', 'Subscription updated.');
    }

    /** Stop a subscription without deleting the record of it. */
    public function cancel(FarmSubscription $subscription)
    {
        if ($subscription->is_cancelled) {
            return back()->with('error', 'That subscription is already cancelled.');
        }

        $subscription->update(['cancelled_at' => now()]);

        return back()->with('success', 'Subscription cancelled.');
    }

    /**
     * Remove a row entered by mistake.
     *
     * Cancelling is almost always the right action; this exists for the case
     * where the subscription was recorded against the wrong farmer entirely
     * and leaving it on their history would be wrong.
     */
    public function destroy(FarmSubscription $subscription)
    {
        $subscription->reminders()->delete();
        $subscription->delete();

        return redirect()
            ->route('subscriptions.index')
            ->with('success', 'Subscription deleted.');
    }
}
