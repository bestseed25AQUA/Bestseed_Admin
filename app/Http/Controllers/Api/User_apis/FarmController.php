<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Farmer;
use App\Models\Farm;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\FeedBackfillService;
use App\Models\Tank;
use App\Models\TankBatch;
use App\Models\Feed;
use App\Models\TankFeedHistory;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Manager;
use App\Models\PushNotification;
use Illuminate\Support\Facades\Log;
use App\Models\FarmImage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse; //added for tank feed report csv
use Illuminate\Support\Facades\Storage; //added for tank csv api
use App\Services\FarmAccessService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;


class FarmController extends Controller
{
    /** Upper bound on rows a single backfill may insert. */

    public function __construct(private readonly FarmAccessService $farmAccess)
    {
    }

    /**
     * Edit one recorded feed entry (meals and quantity).
     *
     * POST /api/farmer/tank-feed-entry  { history_id, tank_id, meals, feed_quantity }
     *
     * Feed lives in two tables with no link column between them:
     * `tank_feed_histories` (what the history screen reads) and `feeds` (what
     * every total is summed from). Editing one alone would leave the screen and
     * the totals disagreeing, so both move together — the `feeds` row is
     * matched on tank, date and the values being replaced.
     */
    public function updateTankFeedEntry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'history_id'    => 'required|integer',
            'tank_id'       => 'required|integer',
            'meals'         => 'required|numeric|min:0',
            'feed_quantity' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $history = TankFeedHistory::where('id', $request->input('history_id'))
                ->where('tank_id', $request->input('tank_id'))
                ->first();

            if (!$history) {
                return response()->json([
                    'status'  => false,
                    'message' => 'That feed entry no longer exists.',
                ], 404);
            }

            $oldMeals    = $history->meals;
            $oldQuantity = $history->feed_quantity;
            $newMeals    = $request->input('meals');
            $newQuantity = $request->input('feed_quantity');

            DB::transaction(function () use (
                $history, $oldMeals, $oldQuantity, $newMeals, $newQuantity
            ) {
                $history->update([
                    'meals'         => $newMeals,
                    'feed_quantity' => $newQuantity,
                ]);

                // Its counterpart in `feeds`. If a tank has two identical
                // entries on one day either will do — they read the same.
                $feed = Feed::where('tank_id', $history->tank_id)
                    ->whereDate('feed_date', $history->feed_date)
                    ->where('meals', $oldMeals)
                    ->where('feed_quantity', $oldQuantity)
                    ->first();

                if ($feed) {
                    $feed->update([
                        'meals'         => $newMeals,
                        'feed_quantity' => $newQuantity,
                    ]);
                }

                // Keep the tank's running total honest.
                Tank::where('id', $history->tank_id)->update([
                    // The CURRENT crop's total, matching what the app shows —
                    // a finished batch's feed is not part of it.
                    'total_feed_used' => (float) Feed::where('tank_id', $history->tank_id)
                        ->when(
                            $history->batch_id,
                            fn ($q) => $q->where('batch_id', $history->batch_id)
                        )
                        ->sum('feed_quantity'),
                ]);
            });

            return response()->json([
                'status'  => true,
                'message' => 'Feed entry updated successfully.',
                'data'    => $history->fresh(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Feed entry update failed', [
                'history_id' => $request->input('history_id'),
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Could not update the feed entry',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete one recorded feed entry.
     *
     * POST /api/farmer/tank-feed-entry/delete  { history_id, tank_id }
     *
     * Mirrors updateTankFeedEntry: the row exists in both `tank_feed_histories`
     * and `feeds`, so both go, and the tank's running total is recomputed from
     * what is left.
     */
    public function deleteTankFeedEntry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'history_id' => 'required|integer',
            'tank_id'    => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $history = TankFeedHistory::where('id', $request->input('history_id'))
                ->where('tank_id', $request->input('tank_id'))
                ->first();

            if (!$history) {
                return response()->json([
                    'status'  => false,
                    'message' => 'That feed entry no longer exists.',
                ], 404);
            }

            DB::transaction(function () use ($history) {
                // Its counterpart in `feeds`, matched the same way an edit does.
                $feed = Feed::where('tank_id', $history->tank_id)
                    ->whereDate('feed_date', $history->feed_date)
                    ->where('meals', $history->meals)
                    ->where('feed_quantity', $history->feed_quantity)
                    ->first();

                $feed?->delete();
                $history->delete();

                Tank::where('id', $history->tank_id)->update([
                    // The CURRENT crop's total, matching what the app shows —
                    // a finished batch's feed is not part of it.
                    'total_feed_used' => (float) Feed::where('tank_id', $history->tank_id)
                        ->when(
                            $history->batch_id,
                            fn ($q) => $q->where('batch_id', $history->batch_id)
                        )
                        ->sum('feed_quantity'),
                ]);
            });

            return response()->json([
                'status'  => true,
                'message' => 'Feed entry deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Feed entry delete failed', [
                'history_id' => $request->input('history_id'),
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Could not delete the feed entry',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Farms the caller owns.
     *
     * Managers and partners belong to a farm, so every manager/partner endpoint
     * is scoped through this. Only the owner manages a farm's team — a manager
     * with edit rights must not be able to appoint further managers.
     */
    /**
     * NULL for a value the form left empty, the trimmed value otherwise.
     *
     * The app posts every text field, so an untouched optional box arrives as
     * "" rather than being absent. Stored as-is that is neither null nor a
     * number, and later casts turn it into 0.
     */
    private function nullIfBlank($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function ownedFarmIds(Request $request): array
    {
        return Farm::where('farmer_id', $request->user()->id)->pluck('id')->all();
    }

    /** A farm_id the caller owns, or abort. */
    private function ownedFarmId(Request $request, $farmId): int
    {
        if (!in_array((int) $farmId, $this->ownedFarmIds($request), true)) {
            throw new HttpResponseException(response()->json([
                'status'  => false,
                'message' => 'That farm is not yours.',
            ], 403));
        }

        return (int) $farmId;
    }

    /**
     * Remove a farm's generated back-history, leaving hand-entered feed alone.
     *
     * The work lives in [FeedBackfillService] because the admin panel creates
     * farms too, and a farm built there must be indistinguishable from one the
     * farmer made themselves.
     */
    private function clearBackfilledFeed(Farm $farm): void
    {
        app(FeedBackfillService::class)->clear($farm);
    }

    /** @see FeedBackfillService::apply() */
    private function backfillPastFeed(Farm $farm, array $tankIds, float $totalUsed): void
    {
        app(FeedBackfillService::class)->apply($farm, $tankIds, $totalUsed);
    }

    /**
     * Apply edits to tanks the farm already has.
     *
     * Each entry is `{id, stocking_date, feed_used_before}`. A tank whose date
     * or figure has changed has its GENERATED history rewritten: the old
     * is_backfill rows go and new ones are written from the new values. Feed
     * the farmer recorded by hand is never touched — it carries no backfill
     * mark — so correcting a stocking date does not cost them their entries.
     *
     * Ids not belonging to this farm are ignored rather than refused, so a
     * stale form cannot reach into another farmer's tanks.
     *
     * @return int  how many tanks were changed
     */
    private function updateExistingTanks(Request $request, Farm $farm): int
    {
        $decoded = json_decode((string) $request->input('existing_tanks_meta', ''), true);

        if (!is_array($decoded) || empty($decoded)) {
            return 0;
        }

        $tanks = Tank::where('farm_id', $farm->id)->get()->keyBy('id');
        $backfill = app(FeedBackfillService::class);
        $today = Carbon::now()->startOfDay();
        $changed = 0;

        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $tank = $tanks->get((int) ($row['id'] ?? 0));
            if (!$tank) {
                continue;
            }

            // Same parsing as a new tank: a future date is treated as unset
            // rather than generating history that has not happened.
            $date = null;
            if (!empty($row['stocking_date'])) {
                try {
                    // A future date is kept: a pond can be set up before it
                    // is stocked. No history is generated for it — the
                    // backfill stops short of dates that have not arrived —
                    // and the tank simply reads Day 0 until the day comes.
                    $date = Carbon::parse($row['stocking_date'])
                        ->startOfDay()
                        ->toDateString();
                } catch (\Throwable $e) {
                    $date = null;
                }
            }

            $used = (float) ($row['feed_used_before'] ?? 0);
            $used = $used > 0 ? $used : 0.0;

            $oldDate = $tank->stocking_date
                ? Carbon::parse($tank->stocking_date)->toDateString()
                : null;
            $oldUsed = $backfill->backfilledTotalFor((int) $tank->id);

            $dateChanged = $oldDate !== $date;
            $usedChanged = abs($oldUsed - $used) > 0.001;

            if (!$dateChanged && !$usedChanged) {
                continue;
            }

            $tank->stocking_date = $date;
            $tank->save();

            // Rewrite, not append.
            $backfill->clearForTank((int) $tank->id);
            $backfill->applyForTank($farm, (int) $tank->id, $date, $used);

            $changed++;
        }

        return $changed;
    }

    /**
     * Append tanks to a farm, each with its own stocking date and prior feed.
     *
     * Numbered on from the highest existing tank, so a farm with Tank1..Tank5
     * gains Tank6 — the new one lands after the last, not renumbered among
     * them. Existing tanks are never touched: their dates and their history
     * stay exactly as they are.
     *
     * @param  array<int, array{stocking_date: ?string, feed_used_before: float}>  $meta
     * @return int  how many tanks were created
     */
    private function appendTanks(Farm $farm, array $meta): int
    {
        if (empty($meta)) {
            return 0;
        }

        // The highest number already in use, not the row count: a farm that
        // has had a tank deleted would otherwise reuse a name still sitting in
        // its feed history.
        $highest = 0;
        foreach (Tank::where('farm_id', $farm->id)->pluck('tank_name') as $name) {
            if (preg_match('/(\d+)\s*$/', (string) $name, $m)) {
                $highest = max($highest, (int) $m[1]);
            }
        }

        $highest = max($highest, (int) Tank::where('farm_id', $farm->id)->count());

        $backfill = app(FeedBackfillService::class);
        $created  = 0;

        foreach ($meta as $row) {
            $tank = new Tank();
            $tank->tank_name = 'Tank' . (++$highest);
            $tank->farm_id = $farm->id;
            $tank->total_feed_used = 0;
            $tank->status = 1;
            $tank->stocking_date = $row['stocking_date'];
            $tank->save();

            // Same flow as a tank created with the farm: a past stocking date
            // gets its own history generated, one row per meal.
            $backfill->applyForTank(
                $farm,
                $tank->id,
                $row['stocking_date'],
                $row['feed_used_before']
            );

            $created++;
        }

        return $created;
    }

    /**
     * Per-tank stocking dates and prior feed, one entry per tank.
     *
     * The app posts `tanks_meta` as a JSON array — a multipart form cannot
     * carry nested arrays cleanly — shaped
     * `[{"stocking_date":"2026-08-20","feed_used_before":"450"}, ...]`.
     *
     * Returns exactly [$tankCount] entries: short input is padded with a null
     * date so a malformed payload cannot silently create fewer tanks than the
     * farmer asked for, and extra entries are ignored.
     *
     * @return array<int, array{stocking_date: ?string, feed_used_before: float}>
     */
    private function tanksMetaFrom(
        Request $request,
        int $tankCount,
        string $field = 'tanks_meta'
    ): array {
        if ($tankCount <= 0) {
            return [];
        }

        $decoded = json_decode((string) $request->input($field, ''), true);
        $decoded = is_array($decoded) ? $decoded : [];

        $today = Carbon::now()->startOfDay();
        $meta  = [];

        for ($i = 0; $i < $tankCount; $i++) {
            $row  = is_array($decoded[$i] ?? null) ? $decoded[$i] : [];
            $date = null;

            if (!empty($row['stocking_date'])) {
                try {
                    // Kept even when it is in the future: a pond can be set
                    // up before it is stocked. The backfill generates nothing
                    // for a date that has not arrived, so the tank just reads
                    // Day 0 until it does.
                    $date = Carbon::parse($row['stocking_date'])
                        ->startOfDay()
                        ->toDateString();
                } catch (\Throwable $e) {
                    $date = null;
                }
            }

            $used = (float) ($row['feed_used_before'] ?? 0);

            $meta[] = [
                'stocking_date'    => $date,
                'feed_used_before' => $used > 0 ? $used : 0.0,
            ];
        }

        return $meta;
    }


    /** A manager/partner row sitting on one of the caller's farms, or abort. */
    private function ownedTeamMember(Request $request, $memberId): Manager
    {
        $member = Manager::whereIn('farm_id', $this->ownedFarmIds($request))
            ->where('id', $memberId)
            ->first();

        if (!$member) {
            throw new HttpResponseException(response()->json([
                'status'  => false,
                'message' => 'That person is not on any of your farms.',
            ], 403));
        }

        return $member;
    }

    //create farm
     public function createFarm(Request $request)
    {
        try {
           // dd('create farm with method type call or fill form');
            
           // Check which method (call or form)
            $type = $request->input('type', 'form'); // default = form
            //dd($type); //call
            //if ($type === 'call') {
            if ($type == 'call') {
                // Validation for call type
                $validator = Validator::make($request->all(), [
                    'farm_name' => 'required|string|max:255',
                    'contact_number' => 'required|string|max:15',
                ]);
            } else {
                // Validation for form type
                $validator = Validator::make($request->all(), [
                    'farm_name' => 'required|string|max:255',
                    // Optional now: the app sends a date per TANK in
                    // `tanks_meta` and the farm's own date is derived from the
                    // earliest of them. Older clients still send this one.
                    'stocking_date' => 'nullable|date',
                    'tanks' => 'required|integer|min:1',
                    // Per-tank stocking dates and prior feed, as a JSON array
                    // of {stocking_date, feed_used_before}. Validated after
                    // decoding, in tanksMetaFrom().
                    'tanks_meta' => 'nullable|string',
                    // Optional: a farmer setting a farm up before any feed has
                    // been delivered has no stock figure to give yet. The
                    // column is already nullable, and they can fill it in
                    // later from the farm's feed-store card.
                    'store' => 'nullable|numeric|min:0',
                    // Optional: the sheet may send it, older clients will not.
                    'low_feed_limit' => 'nullable|numeric|min:0',
                    // Spread across the tanks and the days since stocking, so
                    // it has to be a sane non-negative number — it was
                    // unvalidated, and a negative one wrote negative feed rows.
                    'feed_used_before' => 'nullable|numeric|min:0',
                    // The app posts photos as `farm_image[]`. There WAS an
                    // `images.*` rule on the edit endpoint, but nothing is ever
                    // sent under that name, so every upload reached the move()
                    // below unchecked — any file type, any size.
                    'farm_image'   => 'nullable|array|max:20',
                    'farm_image.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
                ]);
                }
                //dd($validator);
                if ($validator->fails()) {
                        return response()->json([
                            'status' => false,
                            'errors' => $validator->errors()
                        ], 422);
                }

                //dd($request->all());
                // Step 2: Upload file
                /*if ($request->hasFile('farm_image')) {
                    $file = $request->file('farm_image');
                    $extension = $file->extension();

				    $name = rand() . "_" . $file->getClientOriginalName();
				    $name=  rand().'.'.$extension;
                    
                    $image = $file->move(public_path() . '/uploads/images/farms', $name);
                }*/ //single
                //multiple farm image upload beg
                $imagePaths = []; //default

                // Tell the caller when an image was sent but did not arrive.
                // PHP silently drops uploads over upload_max_filesize, so
                // hasFile() goes false and the farm used to be created with no
                // image while the response still said "success". Silence is the
                // worst outcome here: the farmer thinks the photo saved.
                // The app tells us how many images it attached; if none of
                // them arrived, PHP dropped them and we must say so.
                if ((int) $request->input('image_count', 0) > 0
                    && !$request->hasFile('farm_image')) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'The image could not be uploaded. It may be larger than the '
                                   . ini_get('upload_max_filesize') . ' limit this server allows.',
                    ], 422);
                }

                if ($request->hasFile('farm_image')) {
                    foreach($request->file('farm_image') as $k=>$v) {

                        //$file = $request->file('farm_image');
                        $file = $v;

                        // Name the file OURSELVES.
                        //
                        // This used to be `time() . "_" . getClientOriginalName()`,
                        // which hands the caller the extension — and the target
                        // is public_path(), inside the document root. An upload
                        // called "shell.php" landed at a URL the web server
                        // would happily execute. getClientOriginalName() also
                        // carries the client's directory separators and unicode.
                        //
                        // extension() is guessed from the file's CONTENT, not
                        // its name, and the validation above has already
                        // confirmed it is a real image.
                        $extension = $file->extension() ?: 'jpg';
                        $name = time() . '_' . Str::random(24) . '.' . $extension;

                        $image = $file->move(public_path() . '/uploads/images/farms', $name);
                        // Save file path
                        // Whichever host is actually serving this request. The
                        // URL used to be hardcoded to one deployment, so a file
                        // saved on THIS server was handed to the app as a link
                        // to a DIFFERENT one — the image either 404'd or showed
                        // whatever happened to sit at that path over there.
                        $base_url = rtrim(config('app.url'), '/');
                        //$imagePaths[] = 'uploads/images/farms/' . $name; //in loop
                        $imagePaths[] = $base_url.'/uploads/images/farms/' . $name; //in loop 

                    }

                    
    
                }
                //multiple farm image upload end
                // dd($image);
                $tanksMeta = $this->tanksMetaFrom($request, (int) $request->input('tanks'));

                // The farm's own date is the EARLIEST of its tanks'.
                //
                // Tanks each carry their own stocking date now, but plenty of
                // things still read the farm's — reports, the admin panel, the
                // low-feed check — so it has to stay meaningful. The earliest
                // is the day the farm started operating. Falls back to whatever
                // an older client posted.
                $tankDates = array_filter(array_column($tanksMeta, 'stocking_date'));
                $farmStockingDate = !empty($tankDates)
                    ? min($tankDates)
                    : $request->input('stocking_date');

                // Create Farm Record
                $farm = Farm::create([
                    'farm_name' => $request->input('farm_name'),
                    'farmer_id' => $request->user()->id,
                    'stocking_date' => $farmStockingDate,
                    'no_of_tanks' => $request->input('tanks'),
                    // Blank means "not known yet", stored as NULL rather than
                    // the empty string the form posts. `store` is a string
                    // column, so "" would survive and then read back as 0 in
                    // every place that casts it — indistinguishable from a
                    // farmer who really does have nothing in stock.
                    'store' => $this->nullIfBlank($request->input('store')),
                    'low_feed_limit' => $this->nullIfBlank($request->input('low_feed_limit')),
                ]);

                if(!empty($farm)){

                    FarmImage::create(['farm_id'=>$farm->id, 'images' => json_encode($imagePaths)]);

                    //also create no of tanks i/p at farm create
                    //
                    // Each tank carries its OWN stocking date and its own prior
                    // feed. Tanks are stocked as ponds are prepared, not all on
                    // one day, and the old code left tanks.stocking_date NULL
                    // and dated the whole farm from a single field.
                    $tankIds = [];
                    foreach ($tanksMeta as $i => $row) {
                        $tank = new Tank();
                        $tank->tank_name = 'Tank' . ($i + 1);
                        $tank->farm_id = $farm->id;
                        $tank->total_feed_used = 0; //initially at time of create farm
                        $tank->status = 1;
                        $tank->stocking_date = $row['stocking_date'];
                        $tank->save();
                        $tankIds[] = $tank->id;

                        // Open the tank's first crop cycle.
                        //
                        // Feed rows belong to a batch, and the farm's Total
                        // Feed Used counts only the feed of batches still
                        // running. A tank created without one leaves every row
                        // it generates orphaned at batch_id NULL, so the farm
                        // reads 0 kgs however much was actually entered.
                        TankBatch::create([
                            'tank_id'          => $tank->id,
                            'farm_id'          => $farm->id,
                            'batch_no'         => 1,
                            'stocking_date'    => $row['stocking_date'],
                            'feed_used_before' => $row['feed_used_before'] > 0
                                ? $row['feed_used_before']
                                : null,
                            'started_at'       => now(),
                            'ended_at'         => null,
                        ]);
                    }

                    // Tank stocked on a past date: spread that tank's own
                    // "already used" figure across its own days, one row per
                    // meal, so its history is not blank for a tank that has
                    // been running for weeks.
                    //
                    // Deliberately NOT deducted from `store`: the store is the
                    // stock on hand from now on, and feed consumed before the
                    // farm was registered never passed through it.
                    $backfill = app(FeedBackfillService::class);
                    foreach ($tanksMeta as $i => $row) {
                        $backfill->applyForTank(
                            $farm,
                            $tankIds[$i],
                            $row['stocking_date'],
                            $row['feed_used_before']
                        );
                    }

                    // What the farmer entered, kept so the edit form can show
                    // it back — the sum across tanks.
                    $enteredBefore = array_sum(array_column($tanksMeta, 'feed_used_before'));
                    if ($enteredBefore > 0) {
                        $farm->forceFill(['feed_used_before' => $enteredBefore])->save();
                    }
                }

               // dd($farm); 

                return response()->json([
                    'status' => true,
                    'message' => 'Your request was sent. Farm added successfully using ' . $type,
                    'data' => $farm,
                    'farm_images' => $imagePaths
                ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Farm create failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /** farm list beg */
    public function index(Request $request)
    {
        try {
            $farmer = $request->user();

            // Owned farms plus any farm this farmer scanned into with a live
            // grant that carries view access. Managers and partners log in as
            // farmers, so this one query covers all three roles.
            $farms = Farm::with('images')
                ->accessibleBy($farmer->id)
                ->get();

            if ($farms->isEmpty()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'No farms found',
                    'data'    => [],
                ], 404);
            }

            // Resolve every farm's permission in one pass rather than per row.
            $permissions = $this->farmAccess->permissionsForMany($farmer->id, $farms);

            $farms = $farms->map(function ($farm) use ($permissions) {
                $farm->active_tanks    = Tank::where('status', 1)->where('farm_id', $farm->id)->count();
                $farm->inactive_tanks  = Tank::where('status', 0)->where('farm_id', $farm->id)->count();

                // Only tanks with a RUNNING batch count.
                //
                // Deactivating a tank finishes its crop, and a finished crop's
                // feed is history — it drops out of the farm's Total Feed Used
                // rather than inflating it for ever. The rows stay put; they
                // are simply no longer part of what is in progress.
                $farm->total_feed_used = Feed::where('farm_id', $farm->id)
                    ->whereIn(
                        'batch_id',
                        TankBatch::where('farm_id', $farm->id)->open()->pluck('id')
                    )
                    ->sum('feed_quantity');

                // Lets the app hide edit/delete buttons for a partner who only
                // holds view access, instead of finding out via a 403.
                $farm->access = $permissions[$farm->id]->toArray();

                return $farm;
            });

            return response()->json([
                'status'  => true,
                'message' => 'Farm list fetched successfully',
                'data'    => $farms,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    /** farm list end */

    /**update form beg */
    public function update(Request $request, $id)
    {
        //dd($request->all());
        // Validation rules 
        $validator = Validator::make($request->all(), [
            'farm_name'      => 'required|string|max:255',
            'stocking_date'  => 'nullable|date',
            'no_of_tanks'    => 'nullable|integer|min:0',
            // Tanks being added, as a JSON array of
            // {stocking_date, feed_used_before}. Decoded and validated in
            // tanksMetaFrom(); a multipart form cannot carry nested arrays.
            'new_tanks_meta' => 'nullable|string',
            // Corrections to tanks the farm already has, as a JSON array of
            // {id, stocking_date, feed_used_before}.
            'existing_tanks_meta' => 'nullable|string',
            // Optional here for the same reason as on create — and because an
            // edit that only changes the farm name should not force the farmer
            // to invent a stock figure.
            'store' => 'nullable|numeric|min:0',
            'low_feed_limit' => 'nullable|numeric|min:0',
            'feed_used_before' => 'nullable|numeric|min:0',
            // `farm_image`, not `images`.
            //
            // The old rules named a field the app has never sent — uploads go
            // up as `farm_image[]` — so they matched nothing and every file
            // reached move() unvalidated.
            'farm_image'   => 'nullable|array|max:20',
            'farm_image.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120', // 5MB each
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            //$farm = Farm::findOrFail($id);
            $farm = Farm::with('images')->find($id);
            $previous_farm_images= $farm->images->images;
            //dd($previous_farm_images); //farmer_id
            $previous_farm_images_array= json_decode($previous_farm_images);
           // dd($previous_farm_images_array); //farmer_id

            //get farm images
            /*$farm_images= FarmImage::where('farm_id',$farm->id)
                          ->first();*/

            //dd($farm_images); 

            // Update simple fields
            $farm->farm_name = $request->input('farm_name', $farm->farm_name);
            if ($request->has('stocking_date')) {
                $farm->stocking_date = $request->input('stocking_date');
            }
            // Tanks being ADDED, each with its own stocking date and prior
            // feed. Editing has never created tanks — it only wrote the count
            // to the farm — so raising "No. of Tanks" from 5 to 6 changed a
            // number and nothing else: no sixth tank ever appeared.
            //
            // Only additions are accepted. Lowering the count would mean
            // destroying a tank along with every feed row recorded against it,
            // which an edit form should not do silently.
            $newTanks = $this->tanksMetaFrom(
                $request,
                count(json_decode((string) $request->input('new_tanks_meta', ''), true) ?: []),
                'new_tanks_meta'
            );

            $addedTanks = $this->appendTanks($farm, $newTanks);

            // Corrections to the tanks the farm already has.
            $this->updateExistingTanks($request, $farm);

            // Keep the count honest — it is what the app shows and what the
            // next edit starts from.
            $farm->no_of_tanks = Tank::where('farm_id', $farm->id)->count();

            // The farm's own date follows its tanks — the earliest of them.
            // Correcting a tank's stocking date has to move it, or the farm
            // still claims to have started on a day none of its tanks did.
            $earliest = Tank::where('farm_id', $farm->id)
                ->whereNotNull('stocking_date')
                ->min('stocking_date');

            if ($earliest) {
                $farm->stocking_date = Carbon::parse($earliest)->toDateString();
            }
            if ($request->has('store')) {
                $farm->store = $this->nullIfBlank($request->input('store'));
            }
            if ($request->has('low_feed_limit')) {
                $farm->low_feed_limit = $this->nullIfBlank($request->input('low_feed_limit'));
            }

            // (the farm is saved further down; backfill reads the values
            // already set on the model above)

            // Feed already used, entered or corrected while editing.
            //
            // Replaces the previously generated history rather than adding to
            // it: the old generated rows are dropped and new ones written from
            // the new figure. Feed the farmer entered by hand is never touched,
            // because only generated rows carry is_backfill = 1.
            if ($request->has('feed_used_before')) {
                $newTotal = (float) $request->input('feed_used_before', 0);
                $oldTotal = (float) ($farm->feed_used_before ?? 0);

                if (abs($newTotal - $oldTotal) > 0.001) {
                    $this->clearBackfilledFeed($farm);

                    if ($newTotal > 0) {
                        $this->backfillPastFeed(
                            $farm,
                            Tank::where('farm_id', $farm->id)->pluck('id')->all(),
                            $newTotal
                        );
                    }
                }
            }

            //if image is uploaded at the time of edit beg
            //multiple farm image upload beg
                $imagePaths = []; //default
                if ($request->hasFile('farm_image')) {
                    foreach($request->file('farm_image') as $k=>$v) {

                        //$file = $request->file('farm_image');
                        $file = $v;

                        // Name the file OURSELVES.
                        //
                        // This used to be `time() . "_" . getClientOriginalName()`,
                        // which hands the caller the extension — and the target
                        // is public_path(), inside the document root. An upload
                        // called "shell.php" landed at a URL the web server
                        // would happily execute. getClientOriginalName() also
                        // carries the client's directory separators and unicode.
                        //
                        // extension() is guessed from the file's CONTENT, not
                        // its name, and the validation above has already
                        // confirmed it is a real image.
                        $extension = $file->extension() ?: 'jpg';
                        $name = time() . '_' . Str::random(24) . '.' . $extension;

                        $image = $file->move(public_path() . '/uploads/images/farms', $name);
                        // Save file path
                        // Whichever host is actually serving this request. The
                        // URL used to be hardcoded to one deployment, so a file
                        // saved on THIS server was handed to the app as a link
                        // to a DIFFERENT one — the image either 404'd or showed
                        // whatever happened to sit at that path over there.
                        $base_url = rtrim(config('app.url'), '/');
                        //$imagePaths[] = 'uploads/images/farms/' . $name; //in loop
                        $imagePaths[] = $base_url.'/uploads/images/farms/' . $name; //in loop 

                    }

                    
    
                }
                //multiple farm image upload end

                $merged_array= array_merge($previous_farm_images_array,$imagePaths);
            //if image is uploaded at the time of edit end

            
            $farm->save();

            //update farm image
            if($farm){

                    FarmImage::where('farm_id',$farm->id)
                              ->update([ 'images' => json_encode($merged_array)]);

                }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => $addedTanks > 0
                    ? 'Farm updated. ' . $addedTanks . ' tank(s) added.'
                    : 'Farm updated successfully',
                'data' => $farm,
                //'farm_files' => $farm_images->images,
                //'farm_files' => $farm->images->images,
                'farm_files' => $merged_array,
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            // Log the error if you want: \Log::error($e);
            return response()->json([
                'status' => false,
                'message' => 'Failed to update farm',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**update form end */

    /** delete farm soft delete beg */
    public function deleteFarm($id)
{
    try {
        $farm = Farm::find($id);

        if (!$farm) {
            return response()->json([
                'status' => false,
                'message' => 'Farm not found',
            ], 404);
        }

        $farm->delete(); // soft delete

        return response()->json([
            'status' => true,
            'message' => 'Farm deleted successfully (soft deleted)',
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage(),
        ], 500);
    }
    }

    /** delete farm soft delete end */

    /** create tank beg */
     public function createTank(Request $request)
    {
        try {
           //dd('add tank api create');
           //dd($request->all());
           //dd($request->header('farm_id')); //8

           $farm_id= $request->header('farm_id');
            
           
            
                // Validation for tank
                $validator = Validator::make($request->all(), [
                    'tank_name' => 'required|string|max:255',
                    'status' => 'required|integer',
                    'stocking_date' => 'required|date',
                    'meals' => 'required|integer|min:1',
                    'store' => 'required|string|max:255',
                ]);
                    
                
                //dd($validator);
                if ($validator->fails()) {
                        return response()->json([
                            'status' => false,
                            'errors' => $validator->errors()
                        ], 422);
                }

                //create tank
                // Create Farm Record
                $tank = Tank::create([

                    'tank_name' => $request->tank_name,
                    'farm_id' => $farm_id,
                    'status' => $request->status,
                    'stocking_date' => $request->stocking_date,
                    'meals' => $request->meals,
                    'store' => $request->store,
               
                ]);

                
                

                //dd($tank); 

                return response()->json([
                    'status' => true,
                    'message' => 'Tank for farm created successfully ',
                    'data' => $tank,
                ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Tank create failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    /** create tank end*/

    /** add today's tank quanity beg */
   
public function addTodaysQuantity(Request $request){

    //dd($request->all());

    try{

        
       // $tank_id= $request->header('tank_id'); //pass tank id in header. pass tanks.id=5 for test
        $tank_id= $request->tank_id; //pass tank id in header. pass tanks.id=5 for test
        $feed_id= 0; //mean add efeed
        $previousMeals = null;      // values being replaced, when editing
        $previousQuantity = null;
        if($request->feed_id && $request->feed_id > 0){

            $feed_id= $request->feed_id; //update feed

        }

        // get feed date to update feed of particular date if he forget to add feed qty of a particular date
        //
        // Normalised to Y-m-d. `feeds.feed_date` holds a DATETIME while
        // `tank_feed_histories.feed_date` holds a DATE, so an edit that echoed
        // the feed row's value back ("2026-08-24 12:01:15") made whereDate()
        // match nothing — the history row was never found and a duplicate was
        // written instead of the original being updated.
        $feed_date = now()->toDateString(); //default

        if ($request->feed_date) {
            try {
                $feed_date = Carbon::parse($request->feed_date)->toDateString();
            } catch (\Throwable $e) {
                // Unparseable: fall back to today rather than writing rubbish.
                $feed_date = now()->toDateString();
            }
        }

        //get farm details
        $farm_id_detail= Tank::where('id',$tank_id)
                              ->first();

       // dd($farm_id_detailfarm_id_detail);

        $farm_id= @$farm_id_detail->farm_id;
        
        // Validation for tank
        $validator = Validator::make($request->all(), [
                // `numeric`, not `string`: meals is a count, and the string
                // rule let "abc" through to be stored and then read back as 0
                // by the app. Matches updateTankFeedEntry, which had it right.
                'meals' => 'required|numeric|min:0',
                'feed_quantity' => 'required|numeric|min:0',
                // Optional — the app sends it to record a day that was missed.
                // `before_or_equal:today` because feed cannot be given on a day
                // that has not happened; it was unvalidated, and a typo of
                // 2026 as 2062 wrote a row 36 years out that nothing could
                // reach to correct.
                'feed_date' => 'nullable|date|before_or_equal:today',
                'tank_id' => 'required|exists:tanks,id',
            ]);
                
            
            //dd($validator);
            if ($validator->fails()) {
                    return response()->json([
                        'status' => false,
                        'errors' => $validator->errors()
                    ], 422);
            }
            //now create tank feed. feed always create
            $feed= new Feed(); //default add feed tn tank
            $msg= 'Today\'s tank quantity added successfully.';
            if($feed_id > 0){

                $msg= 'Today\'s tank quantity updated successfully.';

                $feed= Feed::where('id',$feed_id)
                              ->where('tank_id',$tank_id)
                              ->orderBy('id','desc')
                              ->first();

                // Remember what is being replaced so the matching history row
                // can be found before these values are overwritten.
                $previousMeals    = $feed->meals ?? null;
                $previousQuantity = $feed->feed_quantity ?? null;

            }
            // The crop cycle this feed belongs to. Without it the row would be
            // orphaned and disappear from the tank, which reads its history by
            // batch.
            $currentBatchId = optional(TankBatch::currentFor((int) $tank_id))->id;

            $feed->meals = $request->meals;
            $feed->feed_quantity = $request->feed_quantity;
            //$feed->feed_date = date('Y-m-d h:i:s');
            $feed->feed_date = $feed_date;
            $feed->tank_id = $tank_id;
            $feed->batch_id = $currentBatchId;
            $feed->farm_id = $farm_id;
            $feed->save();

            if($feed){

                //if feed is there created feed history beg
                //
                // An EDIT must update the history row, not add another one.
                // This always inserted, so correcting an entry left the old
                // values behind and the history screen showed both.
                $tank_feed_history = null;

                if ($feed_id > 0) {
                    $tank_feed_history = TankFeedHistory::where('tank_id', $tank_id)
                        ->whereDate('feed_date', $feed_date)
                        ->where('meals', $previousMeals)
                        ->where('feed_quantity', $previousQuantity)
                        ->first();
                }

                $tank_feed_history = $tank_feed_history ?: new TankFeedHistory();
                $tank_feed_history->meals = $request->meals;
                $tank_feed_history->feed_quantity = $request->feed_quantity;
               // $tank_feed_history->feed_date = date('Y-m-d h:i:s');
                $tank_feed_history->feed_date = $feed_date;
                $tank_feed_history->tank_id = $tank_id;
                $tank_feed_history->batch_id = $currentBatchId;
                $tank_feed_history->farm_id = $farm_id;
                $tank_feed_history->save();
                //if feed is there created feed history end

                // Keep the tank's running total in step with its rows.
                // Recording feed used to leave this column untouched, so a tank
                // fed every day still reported "0 Kgs" on the farm detail card.
                Tank::where('id', $tank_id)->update([
                    // Scoped to the current crop, so the column means the same
                    // thing the app shows rather than the tank's whole life.
                    'total_feed_used' => (float) Feed::where('tank_id', $tank_id)
                        ->when($currentBatchId, fn ($q) => $q->where('batch_id', $currentBatchId))
                        ->sum('feed_quantity'),
                ]);

            
                return response()->json([
                'status' => true,
                'message' => $msg,
                'data' => $feed,
            ], 201);
            }
    } catch(\Exception $e){

        return response()->json([
            'message' => 'Add/Update Quantity failed',
            'error'   => $e->getMessage()
        ], 500);

    }

} 
    
    /** add today's tank quanity end */

    /** update feed quantity of tank beg*/
    public function updateTankQuantity(Request $request){

        //dd('add todays tank quantity');

        try{

            
            $tank_id= $request->header('tank_id'); //pass tank id in header. pass tanks.id=5 for test
            $feed_id= $request->header('feed_id'); //take feeds.id=1 for now to test. it will pass from app end

            //get farm details
            $farm_id_detail= Tank::where('id',$tank_id)
                                  ->first();

            $farm_id= @$farm_id_detail->farm_id;
            
            // Validation for tank
            $validator = Validator::make($request->all(), [
                    // Same corrections as addTodaysQuantity above: meals is a
                    // count, not free text, and a feed date cannot be in the
                    // future. This endpoint has no caller in the app today,
                    // but it is routed and reachable with a valid token.
                    'meals' => 'required|numeric|min:0',
                    'feed_quantity' => 'required|numeric|min:0',
                    'feed_date' => 'nullable|date|before_or_equal:today',
                    'tank_id' => 'required|exists:tanks,id',
                ]);
                    
                
                //dd($validator);
                if ($validator->fails()) {
                        return response()->json([
                            'status' => false,
                            'errors' => $validator->errors()
                        ], 422);
                }
                $currentBatchId = optional(TankBatch::currentFor((int) $tank_id))->id;

                //now update tank feed. (note: feed - user will be update feed after excpiry date of editing feed)
                $feed= Feed::where('id',$feed_id)->first();
                $feed->meals = $request->meals;
                $feed->feed_quantity = $request->feed_quantity;
                $feed->feed_date = date('Y-m-d h:i:s');
                $feed->tank_id = $tank_id;
                $feed->batch_id = $feed->batch_id ?: $currentBatchId;
                $feed->farm_id = $farm_id;
                $feed->save();

                // Same reason as addTodaysQuantity: keep the tank's running
                // total in step with its rows.
                Tank::where('id', $tank_id)->update([
                    // Scoped to the current crop, so the column means the same
                    // thing the app shows rather than the tank's whole life.
                    'total_feed_used' => (float) Feed::where('tank_id', $tank_id)
                        ->when($currentBatchId, fn ($q) => $q->where('batch_id', $currentBatchId))
                        ->sum('feed_quantity'),
                ]);

                if($feed){
                    return response()->json([
                    'status' => true,
                    'message' => 'Tank quantity updated successfully.',
                    'data' => $feed,
                ], 201);
                }
        } catch(\Exception $e){

            return response()->json([
                'message' => 'Update Quantity failed',
                'error'   => $e->getMessage()
            ], 500);

        }

    }

    /** update feed quantity of tank end*/


    /**change tank status beg */
    public function changeTankStatus(Request $request)
{
    try {
        //tanks.id=1 abhi ke liye lo
        $validator = Validator::make($request->all(), [
            'tank_id' => 'required|exists:tanks,id',
            'status' => 'required|in:0,1',
            // Only read when ACTIVATING: a fresh crop needs a date to count
            // its days from, and — if it went in before today — the feed it
            // has already had.
            'stocking_date'    => 'nullable|date',
            'feed_used_before' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $tank = Tank::find($request->tank_id);
        $activating = (int) $request->status === 1;

        // Activating and deactivating are the two ends of a CROP CYCLE, not
        // just a flag.
        //
        // A tank is stocked, fed for weeks, harvested, and stocked again. This
        // used to flip `tanks.status` and nothing else, so the second crop
        // piled onto the first: the running total kept climbing from where the
        // last one finished and the day count never restarted.
        DB::transaction(function () use ($request, $tank, $activating) {
            $open = TankBatch::openFor((int) $tank->id);

            if ($activating) {
                // Already running: nothing to start.
                if (!$open) {
                    $stockingDate = $request->filled('stocking_date')
                        ? Carbon::parse($request->input('stocking_date'))->toDateString()
                        : Carbon::now()->toDateString();

                    $usedBefore = (float) $request->input('feed_used_before', 0);

                    $lastNo = (int) TankBatch::where('tank_id', $tank->id)->max('batch_no');

                    $batch = TankBatch::create([
                        'tank_id'          => $tank->id,
                        'farm_id'          => $tank->farm_id,
                        'batch_no'         => $lastNo + 1,
                        'stocking_date'    => $stockingDate,
                        'feed_used_before' => $usedBefore > 0 ? $usedBefore : null,
                        'started_at'       => now(),
                        'ended_at'         => null,
                    ]);

                    // The tank's own date follows its current crop, so the day
                    // count and the meal schedule start again.
                    $tank->stocking_date = $stockingDate;

                    // Stocked before today: build the history for the days
                    // that have already passed, exactly as a new tank does.
                    if ($usedBefore > 0) {
                        $farm = Farm::find($tank->farm_id);
                        if ($farm) {
                            app(FeedBackfillService::class)->applyForTank(
                                $farm,
                                (int) $tank->id,
                                $stockingDate,
                                $usedBefore,
                                (int) $batch->id
                            );
                        }
                    }
                }
            } elseif ($open) {
                // Harvested. The batch closes and keeps its rows; the tank's
                // totals fall to zero because nothing is running in it.
                $open->ended_at = now();
                $open->save();
            }

            $tank->status = $request->status;
            $tank->save();
        });

        $message = $activating
            ? 'Tank activated. A new batch has started.'
            : 'Tank deactivated. This batch is finished.';

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $tank->fresh(),
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage() 
        ], 500);
    }
}
    /**change tank status end */

    /**farm tank list api beg */
    public function without_feed_farmTanks(Request $request,$farm_id)
   {
       try {
          // dd('tank list of farm');
          //dd($farm_id); 

           $Tanks = Tank::where('farm_id',$farm_id)->get(); // show all tanks of a farm
           //dd($Tanks);

           //latest feed
          // $feed=
       
           
       

           if ($Tanks->isEmpty()) {
               return response()->json([
                   'status' => false,
                   'message' => 'No farms Tanks found',
                   'data' => []
               ], 404);
           }

           return response()->json([
               'status' => true,
               'message' => 'Farm Tank fetched successfully',
               'data' => $Tanks,
               //'farm_images' => $farm_images,
           ], 200);
       } catch (Exception $e) {
           return response()->json([
               'status' => false,
               'message' => 'Something went wrong',
               'error' => $e->getMessage()
           ], 500);
       }
   }
   
   public function old_without_day_farmTanks(Request $request,$farm_id)
   {
       try {
          // dd('tank list of farm');
          //dd($farm_id); 

           $Tanks = Tank::where('farm_id',$farm_id)->get(); // show all tanks of a farm
           //dd($Tanks);

           //latest feed
          
          // Add latest feed data
                      $Tanks = $Tanks->map(function ($Tank) {

                          //total feed used in a farm in all tank of particular farm
                          //$feed = Feed::where('tank_id', $Tank->id)->orderBy('id','desc')->first(); //total feed used in all 
                          $feed = Feed::where('tank_id', $Tank->id)->orderBy('updated_at','desc')->first(); //total feed used in all 
                          $Tank->feed = $feed;
                         
                          return $Tank;
                      });
       
           
       

           if ($Tanks->isEmpty()) {
               return response()->json([
                   'status' => false,
                   'message' => 'No farms Tanks found',
                   'data' => []
               ], 404);
           }

           return response()->json([
               'status' => true,
               'message' => 'Farm Tank fetched successfully',
               'data' => $Tanks,
               //'farm_images' => $farm_images,
           ], 200);
       } catch (Exception $e) {
           return response()->json([
               'status' => false,
               'message' => 'Something went wrong',
               'error' => $e->getMessage()
           ], 500);
       }
   }

   public function farmTanks(Request $request,$farm_id)
   {
       try {
          // dd('tank list of farm');
          //dd($farm_id); 

           $Tanks = Tank::where('farm_id',$farm_id)->get(); // show all tanks of a farm
           //dd($Tanks);

           //latest feed
          
          // The farm's stocking date, read once rather than per tank. Almost
          // no tank carries its own — the date is set on the farm — so the
          // tank's own value is only a per-tank override when present.
          $farmStockingDate = Farm::withTrashed()->where('id', $farm_id)->value('stocking_date');

          // Add latest feed data
            $Tanks = $Tanks->map(function ($Tank) use ($farmStockingDate) {

            // $i=1;

                //total feed used in a farm in all tank of particular farm
                //$feed = Feed::where('tank_id', $Tank->id)->orderBy('id','desc')->first(); //total feed used in all 
                $feed = Feed::where('tank_id', $Tank->id)->orderBy('updated_at','desc')->first(); //total feed used in all 
                // $total_feeds_added_till_date= TankFeedHistory::where('tank_id', $Tank->id)->count();
                //unique days of feed added
                // Number of DISTINCT days this tank was fed.
                //
                // This used to be select+groupBy followed by ->count(). With a
                // GROUP BY, count() runs "select count(*) ... group by date",
                // which returns one row PER GROUP and Eloquent reads only the
                // first — so a tank fed six times on one day reported 6 days
                // instead of 1. Counting distinct dates directly is what was
                // meant.
                // The crop cycle this tank is on: the open one, or the last
                // finished one while the tank sits inactive. EVERYTHING below
                // is scoped to it, so a second crop starts from nothing
                // instead of carrying the first one's totals.
                $batch = TankBatch::currentFor((int) $Tank->id);
                $batchId = $batch?->id;

                $Tank->batch_id = $batchId;
                $Tank->batch_no = $batch?->batch_no;
                $Tank->batch_active = $batch ? $batch->isOpen() : false;
                $Tank->batch_ended_at = optional($batch?->ended_at)->toIso8601String();

                $total_feeds_added_till_date = TankFeedHistory::where('tank_id', $Tank->id)
                                ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
                                ->distinct()
                                ->count(DB::raw('DATE(feed_date)'));

                // This batch's running total, not the tank's lifetime figure.
                $Tank->total_feed_used = (float) Feed::where('tank_id', $Tank->id)
                    ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
                    ->sum('feed_quantity');

                $Tank->feed = $feed;

                // How old the crop is, NOT how many times it was fed.
                //
                // This used to be the count of distinct days that had a feed
                // entry, so a day nobody recorded feed on never counted and
                // the number stalled — a farm stocked on 1 Aug still read
                // "Day 24" on the 27th. Age is measured from the stocking
                // date, whether or not anyone logged feed that day, and the
                // stocking day itself counts as day 1.
                // The CURRENT batch's date first: a tank on its second crop is
                // as old as that crop, not as old as the tank.
                $stockingDate = optional($batch?->stocking_date)->toDateString()
                    ?: ($Tank->stocking_date ?: $farmStockingDate);

                // Day 1 is the stocking day. A tank stocked in the future is
                // Day 0 — not yet started — rather than a negative count.
                $Tank->day = 0;

                if ($stockingDate) {
                    $start = Carbon::parse($stockingDate)->startOfDay();
                    $Tank->day = $start->greaterThan(now()->startOfDay())
                        ? 0
                        : $start->diffInDays(now()->startOfDay()) + 1;
                }

                // The date the app should reckon this tank's age from, already
                // resolved — the tank's own, or the farm's for tanks created
                // before dates were kept per tank. Sent separately from the raw
                // `stocking_date` column so the app never has to guess.
                $Tank->effective_stocking_date = $stockingDate
                    ? Carbon::parse($stockingDate)->toDateString()
                    : null;

                // How many meals today calls for, by the same rule the backfill
                // uses, so the app draws the right number of boxes without
                // re-deriving the schedule and drifting from the server.
                $Tank->todays_meal_count = $Tank->day > 0
                    ? FeedBackfillService::mealsForDay((int) $Tank->day)
                    : 0;

                // The "already used" figure this tank was set up with, read
                // back from the rows it generated. The edit form shows it so
                // the farmer can correct it.
                $Tank->feed_used_before = app(FeedBackfillService::class)
                    ->backfilledTotalFor((int) $Tank->id);

                // Kept separate: the number of days feed was actually recorded.
                $Tank->fed_days = $total_feeds_added_till_date;

                // EVERY entry recorded for this tank today, not just one.
                //
                // `feed` above is the tank's most recent row of ANY date, and
                // the app presented it as today's — so a tank last fed a week
                // ago showed that week-old figure under "Today's feed Update"
                // with an Edit button beside it, and a second meal recorded
                // today replaced the first on screen instead of joining it.
                // Feed is given several times a day, so a day is a LIST.
                $todaysFeed = TankFeedHistory::where('tank_id', $Tank->id)
                                ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
                                ->whereDate('feed_date', now()->toDateString())
                                ->orderBy('id')
                                ->get(['id', 'meals', 'feed_quantity', 'feed_date']);

                $Tank->todays_feed = $todaysFeed->map(fn ($row) => [
                    'id'            => $row->id,
                    'meals'         => $row->meals,
                    'feed_quantity' => $row->feed_quantity,
                    'feed_date'     => $row->feed_date,
                ])->values();

                // Summed here so every screen shows the same day total instead
                // of each adding the rows up its own way.
                $Tank->todays_meals = $todaysFeed->sum(fn ($row) => (float) $row->meals);
                $Tank->todays_quantity = round(
                    $todaysFeed->sum(fn ($row) => (float) $row->feed_quantity),
                    2
                );

                return $Tank;

                //$i++;
            });
       
           
       

           if ($Tanks->isEmpty()) {
               return response()->json([
                   'status' => false,
                   'message' => 'No farms Tanks found',
                   'data' => []
               ], 404);
           }

           return response()->json([
               'status' => true,
               'message' => 'Farm Tank fetched successfully',
               'data' => $Tanks,
               //'farm_images' => $farm_images,
           ], 200);
       } catch (Exception $e) {
           return response()->json([
               'status' => false,
               'message' => 'Something went wrong',
               'error' => $e->getMessage()
           ], 500);
       }
   }
    /**farm tank list api end */

    /** download tank feed report in csv beg */


    /**
     * A day-by-day PDF of one crop cycle, for the tank's Download button.
     *
     * Every date from stocking to today — or to the harvest date once the crop
     * is finished — appears as its own row, including days nobody recorded
     * anything. The gaps are the useful part: a farmer reviewing a season wants
     * to see which days were missed, and a CSV of only the days that happen to
     * have rows hides exactly that.
     */
    public function downloadFeedReport(Request $request)
    {
        $tankId = (int) $request->input('tank_id');

        $tank = Tank::find($tankId);

        if (!$tank) {
            return response()->json([
                'status'  => false,
                'message' => 'That tank no longer exists.',
            ], 404);
        }

        $farm = Farm::withTrashed()->find($tank->farm_id);

        // ONE crop cycle, not the tank's whole life. A report for the batch
        // just harvested used to include every earlier one as well.
        $batch = TankBatch::currentFor($tankId);

        $startDate = optional($batch?->stocking_date)->toDateString()
            ?: ($tank->stocking_date ?: optional($farm)->stocking_date);

        if (!$startDate) {
            return response()->json([
                'status'  => false,
                'message' => 'This tank has no stocking date, so there is nothing to report yet.',
            ], 422);
        }

        $start = Carbon::parse($startDate)->startOfDay();

        // A finished crop stops at its harvest date; a running one runs to
        // today. Either way the report never invents days beyond the crop.
        $isFinished = $batch && $batch->ended_at !== null;

        $end = $isFinished
            ? Carbon::parse($batch->ended_at)->startOfDay()
            : Carbon::now()->startOfDay();

        if ($end->lessThan($start)) {
            $end = $start->copy();
        }

        // Guard a bad date from producing a thousand-page document.
        if ($start->diffInDays($end) > 400) {
            $start = $end->copy()->subDays(400);
        }

        // One query, grouped by day, rather than one per day.
        $byDate = TankFeedHistory::where('tank_id', $tankId)
            ->when($batch, fn ($q) => $q->where('batch_id', $batch->id))
            ->whereBetween('feed_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn ($row) => Carbon::parse($row->feed_date)->toDateString());

        $rows          = [];
        $totalMeals    = 0;
        $totalQuantity = 0.0;
        $fedDays       = 0;
        $day           = 1;

        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            $entries  = $byDate->get($date->toDateString(), collect());
            $meals    = (float) $entries->sum('meals');
            $quantity = (float) $entries->sum('feed_quantity');

            $rows[] = [
                'day'      => $day++,
                'date'     => $date->copy(),
                'meals'    => $meals + 0,
                'quantity' => $quantity,
                'entries'  => $entries->count(),
            ];

            $totalMeals    += $meals;
            $totalQuantity += $quantity;

            if ($entries->isNotEmpty()) {
                $fedDays++;
            }
        }

        $farmer = $farm ? Farmer::find($farm->farmer_id) : null;

        $fileName = sprintf(
            'feed_report_%s_%s.pdf',
            Str::slug($tank->tank_name ?: 'tank'),
            now()->format('Y_m_d_His')
        );

        $pdf = Pdf::loadView('reports.tank-feed', [
            'tank'          => $tank,
            'farm'          => $farm,
            'farmerName'    => $farmer ? trim($farmer->first_name . ' ' . $farmer->last_name) : null,
            'start'         => $start,
            'end'           => $end,
            'isFinished'    => $isFinished,
            'rows'          => $rows,
            'totalMeals'    => $totalMeals + 0,
            'totalQuantity' => $totalQuantity,
            'fedDays'       => $fedDays,
            'generatedAt'   => now(),
        ])->setPaper('a4');

        $folder = public_path('reports');

        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        $pdf->save($folder . '/' . $fileName);

        // Never hardcode the host — the app re-points this at whichever server
        // the build talks to, but it should be right to begin with.
        $downloadUrl = rtrim(config('app.url'), '/') . '/reports/' . $fileName;

        return response()->json([
            'status'        => true,
            'message'       => 'Tank feed report generated successfully.',
            'download_link' => $downloadUrl,
        ], 200);
    }

    /** download tank feed report in csv end */

    /**added code for check low feed limit and send notification beg */
    public function oldwithout_notify_checkFeedLimit($farm_id)
    {
        try {
            // Example: define your minimum threshold
            $lowLimit = 50; // in kg (you can change or fetch from settings table)

            // Calculate total feed quantity of that farm
            $totalFeed = Feed::where('farm_id', $farm_id)->sum('feed_quantity');

            // A farm with no feed recorded yet is NOT skipped: the alert is
            // about how much is left in the store, and a farm can be stocked
            // below its limit from day one. Returning 404 here meant those
            // farms never got warned.
            if (false) {
                return response()->json([
                    'status' => false,
                    'message' => 'No feed data found for this farm.'
                ], 404);
            }

            // Compare with low limit
            if ($totalFeed < $lowLimit) {
                return response()->json([
                    'status' => true,
                    'message' => 'Warning: Feed is below the minimum limit!',
                    'data' => [
                        'farm_id' => $farm_id,
                        'total_feed' => $totalFeed,
                        'limit' => $lowLimit,
                        'status' => 'low'
                    ]
                ], 200);
            }

            // If feed is sufficient
            return response()->json([
                'status' => true,
                'message' => 'Feed quantity is sufficient.',
                'data' => [
                    'farm_id' => $farm_id,
                    'total_feed' => $totalFeed,
                    'limit' => $lowLimit,
                    'status' => 'ok'
                ]
            ], 200);
        } catch (Exception $e) {
            // Handle any unexpected error
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while checking feed limit.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function checkFeedLimit($farm_id)
    {
        try {
            // Example: define your minimum threshold
            $get_low_feed_limit_of_specific_farm= Farm::where('id',$farm_id)->first();

            if (!$get_low_feed_limit_of_specific_farm) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Farm not found',
                    'data'    => [],
                ], 404);
            }

            //$lowLimit = 50; // in kg (you can change or fetch from settings table)
            $store= $get_low_feed_limit_of_specific_farm->store;
            $lowLimit = $get_low_feed_limit_of_specific_farm->low_feed_limit; // in kg (you can change or fetch from settings table)

            // Nothing to warn about until BOTH figures exist.
            //
            // Both columns are nullable. Left as NULL they cast to 0 in the
            // comparison below, so a farm with a stock figure nobody has
            // entered yet computed "0 - 2000 = -2000 remaining, below a limit
            // of 0" and alerted every single time the farm was opened — about
            // a shortage of a quantity the farmer never claimed to have.
            if ($store === null || $store === '' || $lowLimit === null || $lowLimit === '') {
                return response()->json([
                    'status'  => true,
                    'message' => 'Feed limit not set for this farm',
                    'data'    => ['status' => 'ok'],
                ], 200);
            }

            // Calculate total feed quantity of that farm
            $totalFeed = Feed::where('farm_id', $farm_id)->sum('feed_quantity');

            // A farm with no feed recorded yet is deliberately NOT skipped.
            // The warning is about what is LEFT in the store, and a farm can be
            // stocked below its own limit from day one; the old 404 here meant
            // those farms were never warned at all.

            // Compare with low limit
            $feed_remained_in_store= $store - $totalFeed;
            //if ($totalFeed < $lowLimit) {
            //if ($totalFeed < $lowLimit) {
            if ($feed_remained_in_store < $lowLimit) {

                // 3. CREATE MESSAGE
                $title = "Low Feed Alert!";
                $message = "Your feed level is low: " . "You have consumed ".$totalFeed. "and Your low feed limit is ".$lowLimit;

                // 4. SAVE NOTIFICATION IN DATABASE
                //
                // This wrote to `notifications` with user_id = 1. That table
                // holds VENDOR notification preferences and has no such column,
                // so the insert threw and the whole endpoint 500'd — meaning the
                // alert failed precisely when the feed WAS low. Farmer alerts
                // belong in push_notifications, addressed to the farm's owner.
                //
                // Wrapped: failing to record the alert must never stop us
                // telling the farmer about it.
                try {
                    PushNotification::create([
                        'title'          => $title,
                        'body'           => $message,
                        'module'         => 'farm_management',
                        'recipient_type' => 'farmer',
                        'type'           => 'low_feed_limit',
                        'farmer_id'      => $get_low_feed_limit_of_specific_farm->farmer_id,
                        'data'           => [
                            'farm_id'    => (int) $farm_id,
                            'store'      => $store,
                            'consumed'   => $totalFeed,
                            'low_limit'  => $lowLimit,
                        ],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Low-feed notification could not be saved', [
                        'farm_id' => $farm_id,
                        'error'   => $e->getMessage(),
                    ]);
                }

                 // 5. SEND NOTIFICATION USING FCM pending due to fcm

                return response()->json([
                    'status' => true,
                    'message' => 'Warning: Feed is below the minimum limit!',
                    'data' => [
                        'farm_id' => $farm_id,
                        'total_feed' => $totalFeed,
                        'limit' => $lowLimit,
                        'status' => 'low'
                    ]
                ], 200);
            }

            // If feed is sufficient
            return response()->json([
                'status' => true,
                'message' => 'Feed quantity is sufficient.',
                'data' => [
                    'farm_id' => $farm_id,
                    'total_feed' => $totalFeed,
                    'limit' => $lowLimit,
                    'status' => 'ok'
                ]
            ], 200);
        } catch (Exception $e) {
            // Handle any unexpected error
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while checking feed limit.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**added code for check low feed limit and send notification end */

    /** to create manager or partner beg */
    public function createManager(Request $request)
{
    try {
            // dd('create farm with method type call or fill form');
            $msg= '';
       
      
            // Validation for add manager
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                //'phone' => 'required|string|max:20|unique:managers,phone',
                'farm_id' => 'required|integer|exists:farms,id',
                    'phone' => ['required', 'string', 'max:20',
                        Rule::unique('managers', 'phone')
                            ->where('farm_id', $request->input('farm_id'))
                            ->ignore($request->id)],
               // 'password' => 'required|integer|min:1',
                //'read_access' => 'required|in:0,1',
                'create_access' => 'required|in:0,1',
                'view_access' => 'required|in:0,1',
                'edit_access' => 'required|in:0,1',
                'delete_access' => 'required|in:0,1',
               
                
            ]);
            
            //dd($validator);
            if ($validator->fails()) {
                    return response()->json([
                        'status' => false,
                        'errors' => $validator->errors()
                    ], 422);
            }

            // Only the farm's owner may appoint its managers.
            $farmId = $this->ownedFarmId($request, $request->input('farm_id'));

            // Create Manager Record
            /* $manager = Manager::create([
                        'name' => $request->name,
                        'phone' => $request->phone,
                        'create_access' => $request->create_access,
                        'view_access' =>$request->view_access, 
                        'edit_access' => $request->edit_access, 
                        'delete_access' => $request->delete_access, 
                    ]);*/

            if($request->id && $request->id > 0){
            

                //update manager record
                $member = $this->ownedTeamMember($request, $request->id);

                $member->update([
                    'farm_id'       => $farmId,
                    'name'          => $request->name,
                    'phone'         => $request->phone,
                    'view_access'   => $request->view_access,
                    'create_access' => $request->create_access,
                    'edit_access'   => $request->edit_access,
                    'delete_access' => $request->delete_access,
                    'is_partner'    => 0,
                ]);

                $manager = $member->fresh();

                $msg= 'Manager updated successfully';


            }else{


                // Create Manager Record
             $manager = Manager::create([
                        'farm_id'       => $farmId,
                        'name'          => $request->name,
                        'phone'         => $request->phone,
                        'view_access'   => $request->view_access,
                        'create_access' => $request->create_access,
                        'edit_access'   => $request->edit_access,
                        'delete_access' => $request->delete_access,
                        'is_partner'    => 0,
                    ]);

             //dd($manager);
             $msg= 'Manager created successfully';

            }
            


         

            return response()->json([
                'status' => true,
               // 'message' => 'Manager created successfully',
                'message' => $msg,
                'data' => $manager,
                
            ], 201);
    } catch (HttpResponseException $e) {
        // The ownership guards throw this to return a clean 401/403. Without
        // this clause the broad catch below would swallow it into a 500.
        throw $e;
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Manger create failed',
            'error'   => $e->getMessage()
        ], 500);
    }
    }
    /** to create manager or partner end */

    /** mangers list beg */
    public function getManagers(Request $request)
    {
        try {
            

            // Scoped to the caller's own farms. Optionally narrowed to one farm.
            $managers = Manager::where('is_partner', 0)
                ->whereIn('farm_id', $this->ownedFarmIds($request))
                ->when($request->filled('farm_id'),
                    fn ($q) => $q->where('farm_id', $this->ownedFarmId($request, $request->input('farm_id'))))
                ->get();
          
        

            if ($managers->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No Managers found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Managers list fetched successfully',
                'data' => $managers,
                
            ], 200);
        } catch (HttpResponseException $e) {
        // The ownership guards throw this to return a clean 401/403. Without
        // this clause the broad catch below would swallow it into a 500.
        throw $e;
    } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /** managers list end */

    /**createPartner beg */
    public function createPartner(Request $request)
    {
        try {
           // dd('create farm with method type call or fill form');
           $msg= '';
          
                // Validation for add partner
                $validator = Validator::make($request->all(), [
                    'name' => 'required|string|max:255',
                    //'phone' => 'required|string|max:20|unique:managers,phone',
                    'farm_id' => 'required|integer|exists:farms,id',
                    'phone' => ['required', 'string', 'max:20',
                        Rule::unique('managers', 'phone')
                            ->where('farm_id', $request->input('farm_id'))
                            ->ignore($request->id)],
                   // 'password' => 'required|integer|min:1',
                    'create_access' => 'required|in:0,1',
                    'view_access' => 'required|in:0,1',
                    'edit_access' => 'required|in:0,1',
                    'delete_access' => 'required|in:0,1',
                   
                    
                ]);
                
                //dd($validator);
                if ($validator->fails()) {
                        return response()->json([
                            'status' => false,
                            'errors' => $validator->errors()
                        ], 422);
                }

                // Only the farm's owner may appoint its partners.
                $farmId = $this->ownedFarmId($request, $request->input('farm_id'));

                // Create Manager Record
                /* $partner = Manager::create([
                            'name' => $request->name,
                            'phone' => $request->phone,
                            'read_access' => $request->read_access,
                            'view_access' =>$request->view_access, 
                            'edit_access' => $request->edit_access, 
                            'delete_access' => $request->delete_access, 
                            'is_partner' => 1, 
                        ]);*/
                if($request->id && $request->id > 0){
              

                  //update manager record
                  $member = $this->ownedTeamMember($request, $request->id);

                  $member->update([
                      'farm_id'       => $farmId,
                      'name'          => $request->name,
                      'phone'         => $request->phone,
                      'view_access'   => $request->view_access,
                      'create_access' => $request->create_access,
                      'edit_access'   => $request->edit_access,
                      'delete_access' => $request->delete_access,
                      'is_partner'    => 1,
                  ]);

                  $partner = $member->fresh();

                  $msg= 'Partner updated successfully';


              }else{


                  // Create Manager Record
               $partner = Manager::create([
                          'farm_id'       => $farmId,
                          'name'          => $request->name,
                          'phone'         => $request->phone,
                          'view_access'   => $request->view_access,
                          'create_access' => $request->create_access,
                          'edit_access'   => $request->edit_access,
                          'delete_access' => $request->delete_access,
                          'is_partner'    => 1,
                      ]);

               //dd($manager);
               $msg= 'Partner created successfully';

              }


             

                return response()->json([
                    'status' => true,
                    'message' => $msg,
                    'data' => $partner,
                    
                ], 201);
        } catch (HttpResponseException $e) {
        // The ownership guards throw this to return a clean 401/403. Without
        // this clause the broad catch below would swallow it into a 500.
        throw $e;
    } catch (\Exception $e) {
            return response()->json([
                'message' => 'Partner create failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    /**createPartner end */

    /**partner list getPartners beg */
    public function getPartners(Request $request)
    {
        try {
            

            // Scoped to the caller's own farms. Optionally narrowed to one farm.
            $managers = Manager::where('is_partner', 1)
                ->whereIn('farm_id', $this->ownedFarmIds($request))
                ->when($request->filled('farm_id'),
                    fn ($q) => $q->where('farm_id', $this->ownedFarmId($request, $request->input('farm_id'))))
                ->get();
          
        

            if ($managers->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No Partners found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Partners list fetched successfully',
                'data' => $managers,
                
            ], 200);
        } catch (HttpResponseException $e) {
        // The ownership guards throw this to return a clean 401/403. Without
        // this clause the broad catch below would swallow it into a 500.
        throw $e;
    } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**partner list getPartners end */

    /** remove manager access beg removeManagerAccess */
    public function removeManagerAccess(Request $request)
{
    try {
        
       
        // Refuse before touching anything the caller does not own.
        $this->ownedTeamMember($request, $request->manager_id);

        $manager_id= $request->manager_id;
        $data= $request->all();
        $msg= '';
        $manager_access='';
        if(isset($data['create_access'])){
            $read_access= 0;
            $manager_access= Manager::where('id',$manager_id)->first();
            $manager_access->create_access= $data['create_access'];
            $manager_access->save();
            $msg= 'create access has been removed successfully';
        }
        if(isset($data['view_access'])){
            $view_access= 0;
            $manager_access= Manager::where('id',$manager_id)->first();
            $manager_access->view_access= $data['view_access'];
            $manager_access->save();
            $msg= 'view access has been removed successfully';
        }
        if(isset($data['edit_access'])){
            $update_access= 0;
            $manager_access= Manager::where('id',$manager_id)->first();
            $manager_access->edit_access= $data['edit_access'];
            $manager_access->save();
            $msg= 'update access has been removed successfully';
        }

         if(isset($data['delete_access'])){
            $delete_access= 0;
            $manager_access= Manager::where('id',$manager_id)->first();
            $manager_access->delete_access= $data['delete_access'];
            $manager_access->save();
            $msg= 'delete access has been removed successfully';
        }

        return response()->json([
            'status' => true,
            'message' => $msg,
            'data' => $manager_access
        ], 200);

    } catch (HttpResponseException $e) {
        // The ownership guards throw this to return a clean 401/403. Without
        // this clause the broad catch below would swallow it into a 500.
        throw $e;
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage() 
        ], 500);
    }
    }
    /** remove manager access end */

    /** remove partner access beg */
    public function removePartnerAccess(Request $request)
{
    try {
        
       
        // Refuse before touching anything the caller does not own.
        $this->ownedTeamMember($request, $request->partner_id);

        $partner_id= $request->partner_id;
        $data= $request->all();
       // dd($data);
        $msg= '';
        $partner_access=''; 
        if(isset($data['create_access'])){
           // $read_access= 0;
            $partner_access= Manager::where('id',$partner_id)->first();
            $partner_access->create_access= $data['create_access'];
            $partner_access->save();
            $msg= 'create access has been removed successfully';
        }
        if(isset($data['view_access'])){
           // $view_access= 0;
            $partner_access= Manager::where('id',$partner_id)->first();
            $partner_access->view_access= $data['view_access'];
            $partner_access->save();
            $msg= 'view access has been removed successfully';
        }
        if(isset($data['edit_access'])){
           // $update_access= 0;
            $partner_access= Manager::where('id',$partner_id)->first();
            $partner_access->edit_access= $data['edit_access'];
            $partner_access->save();
            $msg= 'update access has been removed successfully';
        }

         if(isset($data['delete_access'])){
           // $delete_access= 0;
            $partner_access= Manager::where('id',$partner_id)->first();
            $partner_access->delete_access= $data['delete_access'];
            $partner_access->save();
            $msg= 'delete access has been removed successfully';
        }

        return response()->json([
            'status' => true,
            'message' => $msg,
            'data' => $partner_access
        ], 200);

    } catch (HttpResponseException $e) {
        // The ownership guards throw this to return a clean 401/403. Without
        // this clause the broad catch below would swallow it into a 500.
        throw $e;
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage() 
        ], 500);
    }
    }

    /** remove partner access end */
    /** get tank feed history beg */
    public function getTankFeedHistory(Request $request)
{
    try {

        $tankId = $request->tank_id;

        // Required: without it this used to return every tank's history.
        if (empty($tankId)) {
            return response()->json([
                'status'  => false,
                'message' => 'tank_id is required',
            ], 422);
        }

        // This tank's CURRENT crop cycle — the open one, or the last finished
        // one while the tank sits inactive so the farmer can still review and
        // download what they just harvested. Earlier batches are the admin
        // panel's business, not the app's.
        $batch = TankBatch::currentFor((int) $tankId);

        $history = TankFeedHistory::where('tank_id', $tankId)
            ->when($batch, fn ($q) => $q->where('batch_id', $batch->id))
            ->orderBy('id', 'DESC')
            ->get();

        // The date THIS TANK's stock went in. The app draws a card for every
        // day from here to today — including days with nothing recorded — so a
        // farmer who missed a day can still go back and enter it, and it sizes
        // each day's meal boxes from the day number this date produces.
        //
        // The tank's own date wins; the farm's is the fallback for tanks
        // created before dates were kept per tank. This used to read the
        // farm's date only, so every tank on a farm claimed the same age no
        // matter when it was actually stocked.
        $tank = Tank::find($tankId);

        // The CURRENT BATCH's date first — a tank on its second crop is as old
        // as that crop, not as old as the tank.
        $stockingDate = optional($batch?->stocking_date)->toDateString()
            ?: ($tank?->stocking_date
                ?: Farm::withTrashed()->where('id', $tank?->farm_id)->value('stocking_date'));

        // Normalised to Y-m-d: a DATE column comes back as a Carbon instance
        // and would serialise with a time, which the app's DateTime.tryParse
        // then reads as a different day in some timezones.
        $stockingDate = $stockingDate
            ? Carbon::parse($stockingDate)->toDateString()
            : null;

        // Which cycle this is, and whether it is still running. A finished
        // batch is shown read-only, with its report still downloadable.
        $batchMeta = [
            'batch_id'  => $batch?->id,
            'batch_no'  => $batch?->batch_no,
            'is_active' => $batch ? $batch->isOpen() : false,
            'ended_at'  => optional($batch?->ended_at)->toIso8601String(),
        ];

        // if no data found
        if ($history->isEmpty()) {
            return response()->json([
                'status'        => false,
                'message'       => 'No record found',
                'stocking_date' => $stockingDate,
                'batch'         => $batchMeta,
                'data'          => []
            ], 404);
        }

        // success response
        return response()->json([
            'status'        => true,
            'message'       => 'Tank feed history fetched successfully',
            'stocking_date' => $stockingDate,
            'batch'         => $batchMeta,
            'data'          => $history
        ], 200);

    } catch (\Exception $e) {

        // error response
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ], 500);
    }
}
    /** get tank feed history end */

    /** get total feed used and store of a particular farm beg */
   public function getTotalFeedandStore(Request $request, $id)
{
    try {

        $farm = Farm::where('id', $id)->first();

        if (!$farm) {
            return response()->json([
                'status' => false,
                'message' => 'No data found',
                'data' => []
            ], 404);
        }

        // Total feed used in this farm — running crops only, matching the farm
        // list. A finished batch's feed is history, not stock in progress.
        $farm->total_feed_used = Feed::where('farm_id', $farm->id)
            ->whereIn(
                'batch_id',
                TankBatch::where('farm_id', $farm->id)->open()->pluck('id')
            )
            ->sum('feed_quantity');

        // What is actually left in the store. `store` is the stock the farmer
        // put in; everything fed since comes out of it. Computed here so the
        // header, the low-feed check and anything else agree on one number.
        //
        // NULL when no stock figure has been entered — `store` is optional, and
        // casting a missing one to 0 reported the farm as thousands of kilos
        // overdrawn rather than simply unknown.
        $farm->remaining_store = ($farm->store === null || $farm->store === '')
            ? null
            : round((float) $farm->store - (float) $farm->total_feed_used, 2);


        return response()->json([
            'status' => true,
            'message' => 'Farm data fetched successfully',
            'data' => $farm
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /** get total feed used and store of a particular farm end */

    /** update total feed and store of a farm beg */
    public function updateTotalFeed(Request $request, $id)
{
    try {

        // -------------------------------
        // 1. Validate incoming request
        // -------------------------------
        $validator = Validator::make($request->all(), [
           // 'total_feed_used' => 'required|numeric|min:0',
            'store' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // -------------------------------
        // 2. Find the farm
        // -------------------------------
        $farm = Farm::find($id);

        if (!$farm) {
            return response()->json([
                'status'  => false,
                'message' => 'Farm not found',
            ], 404);
        }

        // -------------------------------
        // 3. Update values
        // -------------------------------
       // $farm->total_feed_used  = $request->total_feed_used; //dont update otherwisewise calculation get wrong. disable
        $farm->store = $request->store;

        if ($request->filled('low_feed_limit')) {
            $farm->low_feed_limit = $request->input('low_feed_limit');
        }

        $farm->save();

        // -------------------------------
        // 4. Response success
        // -------------------------------
        return response()->json([
            'status'  => true,
            'message' => 'Total feed updated successfully',
            'data'    => $farm
        ], 200);

    } catch (\Exception $e) {

        // -------------------------------
        // 5. Handle unexpected exceptions
        // -------------------------------
        return response()->json([
            'status'  => false,
            'message' => 'Something went wrong',
            'error'   => $e->getMessage()
        ], 500);
    }
    }
    /** update total feed and store of a farm end */

    /** delete manager deleteManager beg */
    public function deleteManager(Request $request)
{
    try { 
        $manager = $this->ownedTeamMember($request, $request->id);

        $manager->delete(); // soft delete

        return response()->json([
            'status' => true,
           // 'message' => 'Manager deleted successfully (soft deleted)',
            'message' => 'Manager deleted successfully',
        ], 200);

    } catch (HttpResponseException $e) {
        // The ownership guards throw this to return a clean 401/403. Without
        // this clause the broad catch below would swallow it into a 500.
        throw $e;
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage(),
        ], 500);
    }
    }

    /** delete manager deleteManager end */

    /** delete partner deletePartner beg */
    public function deletePartner(Request $request)
{
    try { 
        $partner = $this->ownedTeamMember($request, $request->id);

        $partner->delete(); // soft delete

        return response()->json([
            'status' => true,
           // 'message' => 'Manager deleted successfully (soft deleted)',
            'message' => 'Partner deleted successfully',
        ], 200);

    } catch (HttpResponseException $e) {
        // The ownership guards throw this to return a clean 401/403. Without
        // this clause the broad catch below would swallow it into a 500.
        throw $e;
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage(),
        ], 500);
    }
    }
    /** delete partner deletePartner end */
}
