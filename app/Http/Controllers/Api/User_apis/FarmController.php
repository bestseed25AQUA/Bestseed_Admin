<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Farmer;
use App\Models\Farm;
use App\Models\Tank;
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


class FarmController extends Controller
{
    /** Upper bound on rows a single backfill may insert. */
    private const MAX_BACKFILL_ROWS = 5000;

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
                    'total_feed_used' => (float) Feed::where('tank_id', $history->tank_id)
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
                    'total_feed_used' => (float) Feed::where('tank_id', $history->tank_id)
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
     * Only rows written by [backfillPastFeed] carry is_backfill = 1, so a farmer
     * who has been recording daily since keeps every one of those entries when
     * they correct the "feed already used" figure.
     */
    private function clearBackfilledFeed(Farm $farm): void
    {
        // A farm generated before is_backfill existed has no marked rows, so
        // there is no way to tell its generated history from hand-entered feed.
        // Correcting its figure therefore rewrites the farm's feed history
        // wholesale; from then on the marks exist and only generated rows go.
        $legacy = $farm->feed_used_before === null
            && Feed::where('farm_id', $farm->id)->where('is_backfill', 1)->doesntExist();

        DB::transaction(function () use ($farm, $legacy) {
            Feed::where('farm_id', $farm->id)
                ->when(!$legacy, fn ($q) => $q->where('is_backfill', 1))
                ->delete();

            TankFeedHistory::where('farm_id', $farm->id)
                ->when(!$legacy, fn ($q) => $q->where('is_backfill', 1))
                ->delete();

            // Rebuild each tank's running total from whatever survived.
            foreach (Tank::where('farm_id', $farm->id)->pluck('id') as $tankId) {
                Tank::where('id', $tankId)->update([
                    'total_feed_used' => (float) Feed::where('tank_id', $tankId)->sum('feed_quantity'),
                ]);
            }

            $farm->forceFill(['feed_used_before' => null])->save();
        });
    }

    /**
     * Meals per day for a stock that is [$dayNumber] days old (1 = stocking day).
     *
     *   days  1-7   -> 2 meals
     *   days  8-14  -> 3 meals
     *   day  15+    -> 4 meals
     *
     * Feeding steps up as the stock grows, so a backfilled history that used a
     * flat 1 meal a day did not resemble how the farm was actually run.
     */
    private function mealsForDay(int $dayNumber): int
    {
        if ($dayNumber <= 7) {
            return 2;
        }

        if ($dayNumber <= 14) {
            return 3;
        }

        return 4;
    }

    /**
     * Spread feed already used over the days since stocking.
     *
     * A farmer registering a farm that was stocked weeks ago has history the
     * app knows nothing about, so every tank reads "0 kgs, Day 0". They enter
     * one figure — the total fed so far — and it is divided evenly:
     *
     *     per tank         = total / tankCount
     *     per tank per day = per tank / days since stocking
     *
     * A row is written per tank per day so the tank history screen shows every
     * date from the stocking date to today, exactly as if it had been recorded
     * daily. Totals, the Days count and the low-feed check all pick it up
     * because they read these same tables.
     */
    private function backfillPastFeed(Farm $farm, array $tankIds, float $totalUsed): void
    {
        if ($totalUsed <= 0 || empty($tankIds) || empty($farm->stocking_date)) {
            return;
        }

        try {
            $start = Carbon::parse($farm->stocking_date)->startOfDay();
            $today = Carbon::now()->startOfDay();

            // Nothing to spread for a farm stocked today or in the future.
            if ($start->greaterThanOrEqualTo($today)) {
                return;
            }

            $days = $start->diffInDays($today) + 1; // inclusive of both ends

            // A very old stocking date times many tanks could mean a huge
            // number of rows; refuse rather than lock up the request.
            if ($days * count($tankIds) > self::MAX_BACKFILL_ROWS) {
                Log::warning('Skipped feed backfill: too many rows', [
                    'farm_id' => $farm->id,
                    'days'    => $days,
                    'tanks'   => count($tankIds),
                ]);
                return;
            }

            $rowCount = count($tankIds) * $days;

            // Split so the rows add up to EXACTLY what was entered.
            //
            // Rounding each row to 2dp independently drifts: 25000 over 138
            // rows is 181.159420, which rounds to 181.16 and multiplies back to
            // 25000.08. Round DOWN to a base, then hand out the leftover a
            // paisa at a time, so the sum reconciles with the farmer's figure.
            $base      = floor($totalUsed / $rowCount * 100) / 100;
            $remainder = (int) round(($totalUsed - $base * $rowCount) * 100);

            if ($base <= 0 && $remainder <= 0) {
                return;
            }

            $now   = now();
            $rows  = [];
            $index = 0;

            foreach ($tankIds as $tankId) {
                for ($d = 0; $d < $days; $d++) {
                    // The first $remainder rows carry one extra paisa.
                    $quantity = $base + ($index < $remainder ? 0.01 : 0);
                    $index++;

                    $rows[] = [
                        'meals'         => $this->mealsForDay($d + 1),
                        'tank_id'       => $tankId,
                        'farm_id'       => $farm->id,
                        'feed_quantity' => round($quantity, 2),
                        'feed_date'     => $start->copy()->addDays($d)->toDateString(),
                        'is_backfill'   => 1,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }
            }

            DB::transaction(function () use ($rows, $tankIds) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    Feed::insert($chunk);
                    TankFeedHistory::insert($chunk);
                }

                // Keep each tank's running total in step with its own rows —
                // they can differ by a paisa after the remainder is handed out.
                foreach ($tankIds as $tankId) {
                    Tank::where('id', $tankId)->update([
                        'total_feed_used' => (float) Feed::where('tank_id', $tankId)->sum('feed_quantity'),
                    ]);
                }
            });
            // Remember what was entered so the edit form can show it back and
            // so a later correction knows what it is replacing.
            $farm->forceFill(['feed_used_before' => $totalUsed])->save();
        } catch (\Throwable $e) {
            // A farm is still a valid farm without its back-history.
            Log::warning('Feed backfill failed', [
                'farm_id' => $farm->id,
                'error'   => $e->getMessage(),
            ]);
        }
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
                    'stocking_date' => 'required|date',
                    'tanks' => 'required|integer|min:1',
                    'store' => 'required|numeric|min:0',
                    'low_feed_limit' => 'nullable|numeric|min:0',
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
                        $extension = $file->extension();
                        $name = time() . "_" . $v->getClientOriginalName();

                        
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
                // Create Farm Record
                $farm = Farm::create([
                    'farm_name' => $request->input('farm_name'),
                    'farmer_id' => $request->user()->id,
                    'stocking_date' => $request->input('stocking_date'),
                    'no_of_tanks' => $request->input('tanks'),
                    'store' => $request->input('store'),
                    'low_feed_limit' => $request->input('low_feed_limit'),
                ]);

                if(!empty($farm)){

                    FarmImage::create(['farm_id'=>$farm->id, 'images' => json_encode($imagePaths)]);

                    //also create no of tanks i/p at farm create
                    $tankIds = [];
                    for($i=1; $i <= $request->input('tanks'); $i++){
                        $tank = new Tank();
                        $tank->tank_name = 'Tank'.$i;
                        $tank->farm_id = $farm->id;
                        $tank->total_feed_used = 0; //initially at time of create farm
                        $tank->status = 1;
                        $tank->save();
                        $tankIds[] = $tank->id;

                    }

                    // Farm stocked on a past date: spread the feed already used
                    // across the tanks and the days that have passed, so the
                    // tank history is not blank for a farm that has been running
                    // for weeks.
                    $this->backfillPastFeed(
                        $farm,
                        $tankIds,
                        (float) $request->input('feed_used_before', 0)
                    );

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
                $farm->total_feed_used = Feed::where('farm_id', $farm->id)->sum('feed_quantity');

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
            'store' => 'required|numeric|min:0',
            'low_feed_limit' => 'nullable|numeric|min:0',
            // images can be an array of files OR array of url/strings depending on your frontend
            'images'         => 'sometimes|array',
            'images.*'       => 'file|mimes:jpg,jpeg,png,webp|max:5120' // 5MB each
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
            if ($request->has('no_of_tanks')) {
                $farm->no_of_tanks = $request->input('no_of_tanks');
            }
            if ($request->has('store')) {
                $farm->store = $request->input('store');
            }
            if ($request->has('low_feed_limit')) {
                $farm->low_feed_limit = $request->input('low_feed_limit');
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
                        $extension = $file->extension();
                        $name = time() . "_" . $v->getClientOriginalName();

                        
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
                'message' => 'Farm updated successfully',
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
                'meals' => 'required|string|max:255',
                'feed_quantity' => 'required|numeric|min:0',
                //'feed_date' => 'required|date', //no need .from created_at column we will get
                
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
            $feed->meals = $request->meals;
            $feed->feed_quantity = $request->feed_quantity;
            //$feed->feed_date = date('Y-m-d h:i:s');
            $feed->feed_date = $feed_date;
            $feed->tank_id = $tank_id;
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
                $tank_feed_history->farm_id = $farm_id;
                $tank_feed_history->save();
                //if feed is there created feed history end

                // Keep the tank's running total in step with its rows.
                // Recording feed used to leave this column untouched, so a tank
                // fed every day still reported "0 Kgs" on the farm detail card.
                Tank::where('id', $tank_id)->update([
                    'total_feed_used' => (float) Feed::where('tank_id', $tank_id)->sum('feed_quantity'),
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
                    'meals' => 'required|string|max:255',
                    'feed_quantity' => 'required|numeric|min:0',
                    //'feed_date' => 'required|date', //no need .from created_at column we will get
                    
                ]);
                    
                
                //dd($validator);
                if ($validator->fails()) {
                        return response()->json([
                            'status' => false,
                            'errors' => $validator->errors()
                        ], 422);
                }
                //now update tank feed. (note: feed - user will be update feed after excpiry date of editing feed)
                $feed= Feed::where('id',$feed_id)->first();
                $feed->meals = $request->meals;
                $feed->feed_quantity = $request->feed_quantity;
                $feed->feed_date = date('Y-m-d h:i:s');
                $feed->tank_id = $tank_id;
                $feed->farm_id = $farm_id;
                $feed->save();

                // Same reason as addTodaysQuantity: keep the tank's running
                // total in step with its rows.
                Tank::where('id', $tank_id)->update([
                    'total_feed_used' => (float) Feed::where('tank_id', $tank_id)->sum('feed_quantity'),
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
            'status' => 'required|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $tank = Tank::find($request->tank_id);
        $tank->status = $request->status;
        $tank->save();

        $message = $request->status == 1 ? 'Tank activated successfully' : 'Tank deactivated successfully';

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $tank
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
          
          // Add latest feed data
            $days= 1; //first day deefault
            $Tanks = $Tanks->map(function ($Tank) {

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
                $total_feeds_added_till_date = TankFeedHistory::where('tank_id', $Tank->id)
                                ->distinct()
                                ->count(DB::raw('DATE(feed_date)'));
                $Tank->feed = $feed;
                $Tank->day = @$total_feeds_added_till_date;
                
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


    public function downloadFeedReport(Request $request)
    {   
        //$farm_id = $request->farm_id;
        $tank_id = $request->tank_id; //eg-5
        //dd($tank_id);

        // File path
        $fileName = 'tank_feed_report_' . date('Y_m_d_H_i_s') . '.csv';
        //$filePath = 'reports/' . $fileName; //old

        $folderPath = public_path('reports');
       
        $filePath = $folderPath . '/' . $fileName;

        // Fetch data
       /*$feeds = Feed::when($farm_id, function ($query) use ($farm_id) {
            $query->where('farm_id', $farm_id);
        })->get(['id', 'tank_id', 'farm_id', 'feed_quantity', 'meals', 'feed_date']);*/
        
        //$feeds= Feed::where('tank_id',$tank_id)->get();
        $feeds= Feed::where('tank_id',$tank_id)
                    ->orderBy('id','desc')
                    ->get();
        //dd($feeds);
        // Ensure directory exists
       // Storage::makeDirectory('reports'); //original commented allready folder

        

        // Open file in storage
        //$file = fopen(storage_path('app/public/' . $filePath), 'w');

         $file = fopen($filePath, 'w');

        // Write CSV headers
        fputcsv($file, ['ID', 'Tank ID', 'Farm ID', 'Feed Quantity', 'Meals', 'Feed Date']);

        // Write rows
        foreach ($feeds as $feed) {
            fputcsv($file, [
                $feed->id,
                $feed->tank_id,
                $feed->farm_id,
                $feed->feed_quantity,
                $feed->meals,
                $feed->feed_date,
            ]);
        }

        fclose($file);

        // Get file public URL (make sure `php artisan storage:link` is done)
        //$downloadUrl = asset('storage/' . $filePath);
      //  $downloadUrl =asset('storage/' . $filePath);;
      //  $downloadUrl= 'http://127.0.0.1:8000/reports/'.$fileName; //local////http://127.0.0.1:8000/storage/C:\\xampp\\htdocs\\techland_rvindra_code_folder\\best-seeds\\public\\reports/tank_feed_report_2025_11_15_01_22_14.csv"

        // Same reason as the farm images above: never hardcode the host.
        $downloadUrl = rtrim(config('app.url'), '/') . '/reports/' . $fileName;

        return response()->json([
            'status' => true,
            'message' => 'Tank feed report generated successfully.',
            'download_link' => $downloadUrl,
        ]);
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
            //$lowLimit = 50; // in kg (you can change or fetch from settings table)
            $store= $get_low_feed_limit_of_specific_farm->store;
            $lowLimit = $get_low_feed_limit_of_specific_farm->low_feed_limit; // in kg (you can change or fetch from settings table)

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

        $history = TankFeedHistory::where('tank_id', $tankId)
            ->orderBy('id', 'DESC')
            ->get();

        // The date the stock went in. The app draws a card for EVERY day from
        // here to today — including days with nothing recorded — so a farmer
        // who missed a day can still go back and enter it.
        $stockingDate = Tank::where('tanks.id', $tankId)
            ->join('farms', 'farms.id', '=', 'tanks.farm_id')
            ->value('farms.stocking_date');

        // if no data found
        if ($history->isEmpty()) {
            return response()->json([
                'status'        => false,
                'message'       => 'No record found',
                'stocking_date' => $stockingDate,
                'data'          => []
            ], 404);
        }

        // success response
        return response()->json([
            'status'        => true,
            'message'       => 'Tank feed history fetched successfully',
            'stocking_date' => $stockingDate,
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

        // total feed used in this farm
        $farm->total_feed_used = Feed::where('farm_id', $farm->id)->sum('feed_quantity'); 

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
