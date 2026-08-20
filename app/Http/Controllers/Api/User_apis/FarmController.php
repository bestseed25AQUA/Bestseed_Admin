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
    public function __construct(private readonly FarmAccessService $farmAccess)
    {
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
                    for($i=1; $i <= $request->input('tanks'); $i++){
                        $tank = new Tank();
                        $tank->tank_name = 'Tank'.$i;
                        $tank->farm_id = $farm->id;
                        $tank->total_feed_used = 0; //initially at time of create farm
                        $tank->status = 1;
                        $tank->save();

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
        if($request->feed_id && $request->feed_id > 0){

            $feed_id= $request->feed_id; //update feed

        }

        // get feed date to update feed of particular date if he forget to add feed qty of a particular date
        $feed_date= date('Y-m-d h:i:s'); //default
        
        if($request->feed_date){

            //dd('feed_date is set: '.$request->feed_date);

            $feed_date= $request->feed_date;

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
                $tank_feed_history= new TankFeedHistory();
                $tank_feed_history->meals = $request->meals;
                $tank_feed_history->feed_quantity = $request->feed_quantity;
               // $tank_feed_history->feed_date = date('Y-m-d h:i:s');
                $tank_feed_history->feed_date = $feed_date;
                $tank_feed_history->tank_id = $tank_id;
                $tank_feed_history->farm_id = $farm_id;
                $tank_feed_history->save();
                //if feed is there created feed history end

            
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

        // You can filter by tank_id if needed
        $tankId = $request->tank_id;

        $query = TankFeedHistory::query();

        if (!empty($tankId)) {
            $query->where('tank_id', $tankId);
        }

        $history = $query->orderBy('id', 'DESC')->get();

        // if no data found
        if ($history->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No record found',
                'data' => []
            ], 404);
        }

        // success response
        return response()->json([
            'status' => true,
            'message' => 'Tank feed history fetched successfully',
            'data' => $history
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
