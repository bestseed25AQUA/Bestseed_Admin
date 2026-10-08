<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRequest;
use Carbon\Carbon;
use App\Services\FarmLicenceService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];
    /** Cap on the call list, so one bad month cannot render a thousand rows. */
    private const DUE_SOON_LIMIT = 50;

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

        $query = FarmSubscription::with(['farmer', 'coveredFarms']);

        // Superseded terms drop out of the list the way a replaced free trial
        // does: expired AND covering nothing means a renewal has taken over.
        // They stay readable in the farm's history and under the Expired
        // filter, which is where somebody looking for them would go.
        if (!in_array($filter, ['expired', 'replaced'], true)) {
            $query->where(function ($q) {
                $q->has('coveredFarms')
                  ->orWhereDate('expires_at', '>=', now()->toDateString());
            });
        }

        // Filters run in SQL rather than on the collection so the list stays
        // usable once there are thousands of rows.
        match ($filter) {
            'active'    => $query->active(),
            // Each plan's own window, so this list matches the amber rows in it.
            'expiring'  => $query->expiringSoon(),
            'expired'   => $query->expired()->has('coveredFarms'),
            // Terms a renewal has taken over. Kept reachable, but not counted
            // as expired: no farm lost its cover when they ended.
            'replaced'  => $query->expired()->doesntHave('coveredFarms'),
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

        $perPage = $this->perPage($request->input('per_page'));

        $subscriptions = $query
            ->orderByRaw('cancelled_at IS NULL DESC')
            ->orderBy('expires_at', $filter === 'expiring' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->except(['page', 'partial']));

        // The 15-day notice.
        //
        // Separate from the amber rows, which use each package's OWN window —
        // a one-month package warns at 7 days, so it would never appear in a
        // list of "expiring within 15" if that window were used here. This is a
        // flat, deliberate horizon: everyone admin should be ringing now,
        // regardless of what they bought.
        //
        // Loaded whatever the filter, so filtering to "expired" does not hide
        // the people who are about to become expired.
        $noticeDays = (int) config('subscriptions.admin_notice_days', 15);

        $list = [
            'subscriptions'  => $subscriptions,
            'filter'         => $filter,
            'search'         => $search,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            // Every subscription ever recorded, ignoring the filter and the
            // search. An empty table can then say whether NOTHING has been
            // sold yet or merely nothing matches what is being asked for —
            // two situations needing opposite advice. Included in $list, not
            // only on the full page, so the live-filter partial can say it
            // too rather than falling back to blank wording mid-typing.
            'totalAll'       => FarmSubscription::count(),
            // Free trials carry no subscription row, so they are derived and
            // shown alongside. Not paginated with the rest: there is one per
            // farm, and they belong at the top where they can still be acted
            // on before the farm locks.
            'freeTrials'     => $this->freeTrialRows($filter, $search),
        ];

        // The table on its own, for the live filter as the admin types.
        if ($request->boolean('partial')) {
            return view('admin.subscriptions.partials.list', $list);
        }

        return view('admin.subscriptions.index', $list + [
            'counts'     => $this->counts(),
            'noticeDays' => $noticeDays,
            // Its own page name, so paging the notice does not reset the list
            // below it and vice versa.
            'dueSoon'    => $this->dueSoon($noticeDays),
            'dueSoonCap' => self::DUE_SOON_LIMIT,
        ]);
    }

    /**
     * Farms on the free trial, shaped like a subscription row.
     *
     * The list is what admin reads to see who needs ringing, and a farmer
     * still inside their free period needs ringing BEFORE it lapses — not
     * after, when the farm has already gone read-only. They have no
     * subscription row, so one is derived from the farm itself.
     */
    private function freeTrialRows(string $filter, string $search)
    {
        if (in_array($filter, ['cancelled'], true)) {
            return collect();
        }

        $today = now()->toDateString();

        $farms = Farm::with('farmer')
            ->whereNull('covered_by_subscription_id')
            ->where('took_free_slot', true)
            ->whereNotNull('free_until')
            ->when($search !== '', function ($q) use ($search) {
                $digits = preg_replace('/\D/', '', $search);

                $q->where(function ($inner) use ($search, $digits) {
                    $inner->where('farm_name', 'like', "%{$search}%")
                        ->orWhereHas('farmer', function ($f) use ($search, $digits) {
                            $f->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%");

                            if ($digits !== '') {
                                $f->orWhere('mobile', 'like', "%{$digits}%");
                            }
                        });
                });
            })
            ->when($filter === 'expired', fn ($q) => $q->whereDate('free_until', '<', $today))
            ->when(
                in_array($filter, ['active', 'expiring'], true),
                fn ($q) => $q->whereDate('free_until', '>=', $today)
            )
            ->when(
                $filter === 'expiring',
                fn ($q) => $q->whereDate('free_until', '<=', now()->addDays(15)->toDateString())
            )
            ->orderBy('free_until')
            ->get();

        return $farms->map(function (Farm $farm) {
            $ends = Carbon::parse($farm->free_until)->startOfDay();
            $days = (int) Carbon::today()->diffInDays($ends, false);

            return (object) [
                'is_free'        => true,
                'farm'           => $farm,
                'farmer'         => $farm->farmer,
                'plan_label'     => 'Free trial',
                'amount'         => 0,
                'starts_at'      => $farm->created_at,
                'expires_at'     => $ends,
                'days_remaining' => $days,
                'state'          => $days < 0 ? 'expired' : ($days <= 15 ? 'expiring' : 'active'),
            ];
        });
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
        // Free trials counted alongside packages. They are farms about to stop
        // working, which is the question these cards answer — counting only
        // sales said "1 expiring" with two farms twelve days from locking.
        $today  = now()->toDateString();
        $notice = now()->addDays(15)->toDateString();

        $trials = Farm::whereNull('covered_by_subscription_id')
            ->where('took_free_slot', true)
            ->whereNotNull('free_until');

        return [
            'active'   => FarmSubscription::active()->has('coveredFarms')->count()
                + (clone $trials)->whereDate('free_until', '>=', $today)->count(),
            'expiring' => FarmSubscription::expiringSoon()->has('coveredFarms')->count()
                + (clone $trials)
                    ->whereDate('free_until', '>=', $today)
                    ->whereDate('free_until', '<=', $notice)
                    ->count(),
            'expired'  => FarmSubscription::expired()->has('coveredFarms')->count()
                + (clone $trials)->whereDate('free_until', '<', $today)->count(),
        ];
    }

    /**
     * The renewal call list: every farm whose cover runs out within the
     * notice window, soonest first.
     *
     * Farms, not sales. A free trial about to end is a farm about to stop
     * working, and the admin needs to ring that farmer exactly as much as one
     * whose package is lapsing.
     */
    private function dueSoon(int $noticeDays)
    {
        $limit = now()->addDays($noticeDays)->toDateString();
        $today = now()->toDateString();

        $packages = FarmSubscription::with(['farmer', 'coveredFarms'])
            ->expiringWithin($noticeDays)
            ->get()
            ->map(fn (FarmSubscription $s) => (object) [
                'farmer'     => $s->farmer,
                'label'      => $s->plan_label,
                'is_free'    => false,
                'farms'      => $s->coveredFarms->pluck('farm_name')->implode(', ')
                    ?: 'Not assigned',
                'expires_at' => $s->expires_at,
                'days'       => $s->days_remaining,
                'renew_url'  => route('subscriptions.renew.form', $s),
            ]);

        $trials = Farm::with('farmer')
            ->whereNull('covered_by_subscription_id')
            ->where('took_free_slot', true)
            ->whereNotNull('free_until')
            ->whereDate('free_until', '>=', $today)
            ->whereDate('free_until', '<=', $limit)
            ->get()
            ->map(fn (Farm $f) => (object) [
                'farmer'     => $f->farmer,
                'label'      => 'Free trial',
                'is_free'    => true,
                'farms'      => $f->farm_name,
                'expires_at' => Carbon::parse($f->free_until),
                'days'       => (int) Carbon::today()->diffInDays(Carbon::parse($f->free_until), false),
                'renew_url'  => route('subscriptions.create', [
                    'farmer' => $f->farmer_id,
                    'farm'   => $f->id,
                ]),
            ]);

        return $packages->concat($trials)->sortBy('days')->values();
    }
    /** Keeps a hand-typed per_page out of the query string. */
    private function perPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, self::PER_PAGE_OPTIONS, true) ? $value : 25;
    }

    public function create(Request $request)
    {
        $farmerId = $request->input('farmer_id', $request->input('farmer'));
        $farmer   = $farmerId ? Farmer::find($farmerId) : null;

        return view('admin.subscriptions.create', [
            'plans'    => SubscriptionPlan::active()->ordered()->get(),
            'currency' => config('subscriptions.currency_symbol', '₹'),
            // `farmer` as well as `farmer_id`: the Sell button on the requests
            // queue sends the short name, and reading only the long one left
            // the admin re-typing a mobile number the page already knew.
            'farmer'   => $farmer,
            'farms'    => $farmer
                ? Farm::where('farmer_id', $farmer->id)->orderBy('id')->get()
                : collect(),
            'farmId'   => $request->input('farm'),
            'planKey'  => $request->input('plan'),
        ]);
    }

    /**
     * GET /admin/subscriptions/lookup?mobile=...
     *
     * Find the caller by their number so the admin never types a farmer id.
     * Returns their current standing too, so the person on the phone can say
     * "you already have until the 12th" before taking money twice.
     */
    /** Matching farmers as the admin types, by mobile or name. */
    public function lookupFarmer(Request $request)
    {
        $term   = trim((string) $request->input('q', $request->input('mobile', '')));
        $digits = preg_replace('/\D/', '', $term);

        if (mb_strlen($term) < 3 && mb_strlen($digits) < 3) {
            return response()->json(['status' => true, 'farmers' => []]);
        }

        $farmers = Farmer::query()
            ->when($digits !== '', fn ($q) => $q->where('mobile', 'like', "%{$digits}%"))
            ->when($digits === '', function ($q) use ($term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            // An exact number first, then the shortest numbers, so a full
            // 10-digit entry puts its one true match at the top.
            ->orderByRaw('CASE WHEN mobile = ? THEN 0 ELSE 1 END', [$digits ?: '-'])
            ->orderBy('mobile')
            ->limit(8)
            ->get(['id', 'first_name', 'last_name', 'mobile']);

        if ($farmers->isEmpty()) {
            return response()->json(['status' => true, 'farmers' => []]);
        }

        $ids = $farmers->pluck('id');

        $farmCounts = Farm::whereIn('farmer_id', $ids)
            ->selectRaw('farmer_id, COUNT(*) AS total')
            ->groupBy('farmer_id')
            ->pluck('total', 'farmer_id');

        // The farms themselves, so choosing a farmer fills the farm picker
        // without a second request.
        $farmsByFarmer = Farm::whereIn('farmer_id', $ids)
            ->orderBy('id')
            ->get(['id', 'farmer_id', 'farm_name'])
            ->groupBy('farmer_id');

        $live = FarmSubscription::whereIn('farmer_id', $ids)
            ->active()
            ->get()
            ->groupBy('farmer_id');

        return response()->json([
            'status'  => true,
            'farmers' => $farmers->map(function (Farmer $farmer) use ($farmCounts, $live, $farmsByFarmer) {
                $held = $live->get($farmer->id);

                return [
                    'id'     => $farmer->id,
                    'name'   => trim($farmer->first_name . ' ' . $farmer->last_name) ?: 'Unnamed farmer',
                    'mobile' => $farmer->mobile,
                    'farms'  => (int) ($farmCounts[$farmer->id] ?? 0),
                    'farm_list' => ($farmsByFarmer[$farmer->id] ?? collect())
                        ->map(fn ($f) => ['id' => $f->id, 'name' => $f->farm_name])
                        ->values()
                        ->all(),
                    'active' => $held && $held->isNotEmpty() ? [
                        'count'          => $held->count(),
                        'plan_label'     => $held->first()->plan_label,
                        'expires_on'     => $held->sortByDesc('expires_at')->first()->expires_at->format('d M Y'),
                        'days_remaining' => $held->sortByDesc('expires_at')->first()->days_remaining,
                    ] : null,
                ];
            })->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'farmer_id' => ['required', 'integer', 'exists:farmers,id'],
            // Which farm the package covers. Optional, because a package can
            // be recorded ahead of the farmer choosing — but without it the
            // farm stays locked after paying, so the form preselects one.
            'farm_id'   => ['nullable', 'integer', 'exists:farms,id'],
            'plan_key'  => ['required', Rule::exists('subscription_plans', 'key')->where('is_active', true)],
            // Both dates are the admin's to choose. Left blank, the service
            // starts it today — or the day after an existing term ends, so
            // recording a renewal early does not waste days already paid for —
            // and ends it by the package's months.
            'starts_at'  => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes'      => ['nullable', 'string', 'max:1000'],
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
                expiresAt: $validated['expires_at'] ?? null,
            );
        } catch (\Throwable $e) {
            Log::error('Subscription could not be recorded', [
                'farmer_id' => $validated['farmer_id'],
                'plan_key'  => $validated['plan_key'],
                'error'     => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'The subscription could not be saved. Please try again.');
        }

        $covered = null;

        if (!empty($validated['farm_id'])) {
            $farm = Farm::where('id', $validated['farm_id'])
                ->where('farmer_id', $validated['farmer_id'])
                ->first();

            // Only the farmer's own farm, so a mistyped id cannot unlock
            // somebody else's.
            if ($farm) {
                app(FarmLicenceService::class)->attach($farm, $subscription);
                $covered = $farm->farm_name;

                // The ask has been answered, so the queue should stop showing
                // it. Left open, the admin rings a farmer who already paid.
                SubscriptionRequest::where('farmer_id', $validated['farmer_id'])
                    ->where('farm_id', $farm->id)
                    ->open()
                    ->update([
                        'status'     => SubscriptionRequest::DONE,
                        'handled_at' => now(),
                    ]);
            }
        }

        $until = $subscription->expires_at->format('d M Y');

        return redirect()
            ->route('subscriptions.index')
            ->with('success', $covered
                ? "{$subscription->plan_label} recorded for \"{$covered}\". Covered until {$until}."
                : "{$subscription->plan_label} recorded. Active until {$until}.");
    }

    /**
     * Requests farmers have left from the app.
     *
     * Its own screen rather than a filter on the subscriptions list: these are
     * not subscriptions, they are people waiting to be sold one, and burying
     * them behind a dropdown is how a queue goes unworked.
     */
    public function requests(Request $request)
    {
        $status = $request->input('status', 'open');

        $query = SubscriptionRequest::with(['farmer', 'farm', 'plan']);

        match ($status) {
            'open'     => $query->open(),
            'pending'  => $query->pending(),
            'done'     => $query->where('status', SubscriptionRequest::DONE),
            'declined' => $query->where('status', SubscriptionRequest::DECLINED),
            default    => null,
        };

        return view('admin.subscriptions.requests', [
            'requests' => $query->orderByRaw("FIELD(status, 'pending','contacted','done','declined')")
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'status' => $status,
            'counts' => [
                'open'    => SubscriptionRequest::open()->count(),
                'pending' => SubscriptionRequest::pending()->count(),
                // Every request ever, so an empty list can tell "nobody has
                // ever asked" apart from "nothing matches THIS filter" — two
                // situations that call for completely different wording.
                'total'   => SubscriptionRequest::count(),
            ],
        ]);
    }

    /** Move one request along, with a note of what was done. */
    public function updateRequest(Request $request, SubscriptionRequest $subscriptionRequest)
    {
        $validated = $request->validate([
            'status'     => ['required', Rule::in([
                SubscriptionRequest::PENDING,
                SubscriptionRequest::CONTACTED,
                SubscriptionRequest::DONE,
                SubscriptionRequest::DECLINED,
            ])],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $subscriptionRequest->update([
            'status'     => $validated['status'],
            'admin_note' => $validated['admin_note'] ?? $subscriptionRequest->admin_note,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);

        return back()->with('success', 'Request updated.');
    }

    /**
     * The renew form for one subscription.
     *
     * A NEW term rather than an edit of the old one: the farmer paid twice, and
     * the history should say so. The old row stays exactly as sold.
     */
    public function renewForm(FarmSubscription $subscription)
    {
        $subscription->load('farmer');

        // Prefilled, not imposed. Starts the day after the current term ends,
        // so nothing already paid for is thrown away, and runs the package's
        // months from there — both dates remain the admin's to change.
        $start = $subscription->expires_at->isFuture()
            ? $subscription->expires_at->copy()->addDay()
            : Carbon::today();

        $plan = SubscriptionPlan::where('key', $subscription->plan_key)->first();
        $months = $plan?->months ?? $subscription->months ?? 1;

        return view('admin.subscriptions.renew', [
            'subscription' => $subscription,
            'plans'        => SubscriptionPlan::active()->ordered()->get(),
            'currency'     => config('subscriptions.currency_symbol', '₹'),
            'startsAt'     => $start->toDateString(),
            'expiresAt'    => $start->copy()->addMonths($months)->subDay()->toDateString(),
        ]);
    }

    public function renew(Request $request, FarmSubscription $subscription)
    {
        $validated = $request->validate([
            'plan_key'   => ['required', Rule::exists('subscription_plans', 'key')->where('is_active', true)],
            'starts_at'  => ['required', 'date'],
            'expires_at' => ['required', 'date'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ], [
            'starts_at.required'  => 'Choose the date the new term starts.',
            'expires_at.required' => 'Choose the date the new term ends.',
        ]);

        try {
            $renewed = $this->subscriptions->subscribe(
                farmerId:  (int) $subscription->farmer_id,
                planKey:   $validated['plan_key'],
                startsAt:  $validated['starts_at'],
                notes:     $validated['notes'] ?? null,
                createdBy: auth()->id(),
                expiresAt: $validated['expires_at'],
            );
        } catch (\Throwable $e) {
            Log::error('Subscription could not be renewed', [
                'subscription_id' => $subscription->id,
                'error'           => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'The renewal could not be saved. Please try again.');
        }

        // The farms the old term covered move onto the new one.
        //
        // Without this a renewal left the farm pointing at the lapsed term —
        // still locked, while the package just paid for sat covering nothing
        // and read "Not assigned" in the list.
        $licence = app(FarmLicenceService::class);
        $moved   = [];

        foreach ($subscription->coveredFarms as $farm) {
            $licence->attach($farm, $renewed);
            $moved[] = $farm->farm_name;
        }

        $until = $renewed->expires_at->format('d M Y');

        return redirect()
            ->route('subscriptions.show', $renewed)
            ->with('success', $moved === []
                ? "{$renewed->plan_label} recorded until {$until}."
                : "{$renewed->plan_label} recorded. \"" . implode('", "', $moved)
                    . "\" covered until {$until}.");
    }

    public function show(FarmSubscription $subscription)
    {
        $subscription->load('farmer', 'reminders', 'coveredFarms');

        return view('admin.subscriptions.show', [
            'subscription' => $subscription,
            'farms'        => Farm::where('farmer_id', $subscription->farmer_id)->count(),
            // What has covered each of these farms over time, so the page
            // answers "what was this farm on before?" without hunting through
            // the list.
            'history'      => $subscription->coveredFarms
                ->mapWithKeys(fn (Farm $farm) => [$farm->id => $this->coverHistory($farm)])
                ->all(),
        ]);
    }

    /**
     * Everything that has ever covered one farm, newest first.
     *
     * The free period is included: it is the farm's first cover, and leaving
     * it out makes a farm look as though nothing held it before the first
     * package was sold.
     */
    private function coverHistory(Farm $farm): array
    {
        // From the cover record, not from what points at the farm today: a
        // renewal moves that pointer, and reading it loses every term the farm
        // has already had.
        $periods = DB::table('farm_cover_periods')
            ->where('farm_id', $farm->id)
            ->orderByDesc('started_on')
            ->orderByDesc('id')
            ->get();

        $subs = FarmSubscription::whereIn(
            'id',
            $periods->pluck('subscription_id')->filter()->all()
        )->get()->keyBy('id');

        return $periods->map(function ($period) use ($subs) {
            $sub = $period->subscription_id ? $subs->get($period->subscription_id) : null;

            return [
                'id'      => $sub?->id,
                'label'   => $period->is_free ? 'Free trial' : ($sub?->plan_label ?? 'Package'),
                'amount'  => (float) ($sub?->amount ?? 0),
                'starts'  => $period->started_on ? Carbon::parse($period->started_on) : null,
                // The period's own end, which for a replaced term is the day it
                // was replaced rather than the day it would have run to.
                'ends'    => $period->ended_on
                    ? Carbon::parse($period->ended_on)
                    : $sub?->expires_at,
                'state'   => $period->ended_on ? 'ended' : 'current',
                'is_free' => (bool) $period->is_free,
            ];
        })->all();
    }

    public function edit(FarmSubscription $subscription)
    {
        return view('admin.subscriptions.edit', [
            'subscription' => $subscription->load('farmer'),
            'plans'        => SubscriptionPlan::active()->ordered()->get(),
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
            'plan_key'   => ['required', Rule::exists('subscription_plans', 'key')],
            'starts_at'  => ['required', 'date'],
            'expires_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ], [
            'expires_at.after_or_equal' => 'The end date cannot be before the start date.',
        ]);

        $plan = SubscriptionPlan::where('key', $validated['plan_key'])->firstOrFail();

        $subscription->update([
            'plan_id'    => $plan->id,
            'plan_key'   => $plan->key,
            'plan_label' => $plan->label,
            'farm_limit' => (int) $plan->farm_limit,
            'amount'     => $plan->amount,
            'months'     => (int) $plan->months,
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
        // Let go of the farms first. Without this they keep pointing at a row
        // that no longer exists: the farm reads as locked with no package to
        // name, and nothing in the panel explains why.
        $released = $subscription->coveredFarms->pluck('farm_name')->all();

        Farm::where('covered_by_subscription_id', $subscription->id)
            ->update(['covered_by_subscription_id' => null]);

        DB::table('farm_cover_periods')
            ->where('subscription_id', $subscription->id)
            ->delete();

        $subscription->reminders()->delete();
        $subscription->delete();

        return redirect()
            ->route('subscriptions.index')
            ->with('success', $released === []
                ? 'Subscription deleted.'
                : 'Subscription deleted. "' . implode('", "', $released)
                    . '" no longer has cover.');
    }
}
