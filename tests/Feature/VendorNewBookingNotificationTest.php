<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\Hatchery;
use App\Models\HatcheryLocation;
use App\Models\Vendor;
use App\Services\FirebaseNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Captures every sendToDevice() call instead of talking to FCM.
 * Deliberately does NOT call parent::__construct(), which would resolve
 * 'firebase.messaging' and need real service-account credentials.
 */
class FakeFirebaseNotificationService extends FirebaseNotificationService
{
    /** @var array<int, array{token:string,title:string,body:string,image:?string,data:array}> */
    public array $calls = [];

    public function __construct()
    {
        // no-op
    }

    public function sendToDevice(string $fcmToken, string $title, string $body, ?string $imageUrl = null, array $data = []): bool
    {
        $this->calls[] = [
            'token' => $fcmToken,
            'title' => $title,
            'body'  => $body,
            'image' => $imageUrl,
            'data'  => $data,
        ];

        return true;
    }
}

/**
 * The vendor's "New Booking received" push must show only:
 *
 *     booking id : #1014
 *     hatchery name : Apex Hatchery
 *
 * - no "booked by" line (the customer's name/number must not be exposed), and
 * - the SHORT numeric id the admin panel lists, not the long OD-BS-... uid.
 */
class VendorNewBookingNotificationTest extends TestCase
{
    private const CUSTOMER_MOBILE = '8328537731';
    private const CUSTOMER_NAME   = 'Ravi Kumar';
    private const HATCHERY_NAME   = 'Apex Hatchery';

    private FakeFirebaseNotificationService $fcm;

    protected function setUp(): void
    {
        parent::setUp();

        // Built by hand rather than via migrate:fresh — three tables in this
        // project are created by two migrations each, so a fresh migrate aborts.
        Schema::dropIfExists('bookings');
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_uid')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('hatchery_id')->nullable();
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_mobile')->nullable();
            $table->string('hatchery_name')->nullable();
            $table->string('hatchery_location')->nullable();
            $table->string('unit')->nullable();
            $table->integer('no_of_pieces')->nullable();
            $table->string('dropping_location')->nullable();
            $table->decimal('drop_lat', 12, 8)->nullable();
            $table->decimal('drop_lng', 12, 8)->nullable();
            $table->date('packing_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->timestamp('delivery_datetime')->nullable();
            $table->timestamp('delivery_expected')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('estimated_price', 12, 2)->nullable();
            $table->integer('salinity')->nullable();
            $table->integer('is_spot')->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::dropIfExists('vendors');
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('mobile')->nullable();
            $table->string('address')->nullable();
            $table->string('pincode')->nullable();
            $table->string('best_seeds_id')->nullable();
            $table->text('fcm_token')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::dropIfExists('farmers');
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('mobile')->nullable();
            $table->text('fcm_token')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::dropIfExists('hatcheries');
        Schema::create('hatcheries', function (Blueprint $table) {
            $table->id();
            $table->string('hatchery_name')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('is_spot')->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('hatchery_locations');
        Schema::create('hatchery_locations', function (Blueprint $table) {
            $table->id();
            $table->string('location_name')->nullable();
            $table->string('state_code')->nullable();
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('hatchery_categories');
        Schema::create('hatchery_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_name')->nullable();
            $table->timestamps();
        });

        // sendBookingNotification() (the customer-side push) logs into this
        // table before the vendor push runs.
        Schema::dropIfExists('push_notifications');
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->text('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Container-resolved in the controller, so this swap is what actually
        // runs. No network, no credentials.
        $this->fcm = new FakeFirebaseNotificationService();
        $this->app->instance(FirebaseNotificationService::class, $this->fcm);
    }

    /**
     * A real vendor (with a device token), their hatchery, and a logged-in
     * customer — then the actual POST the user app makes.
     *
     * @return array{0: \Illuminate\Testing\TestResponse, 1: \App\Models\Vendor}
     */
    private function placeBooking(string $endpoint = '/api/farmer/book-hatchery', array $overrides = []): array
    {
        $vendor = Vendor::create([
            'name'      => 'Apex Vendor',
            'mobile'    => '9000000009',
            'fcm_token' => 'VENDOR_DEVICE_TOKEN_123',
            'status'    => 1,
        ]);

        $location = HatcheryLocation::create([
            'location_name' => 'Nellore',
            'state_code'    => 'TS',
        ]);

        $hatchery = Hatchery::create([
            'hatchery_name' => self::HATCHERY_NAME,
            'vendor_id'     => $vendor->id,
            'location_id'   => $location->id,
            'is_active'     => true,
        ]);

        $categoryId = DB::table('hatchery_categories')->insertGetId([
            'category_name' => 'Vannamei',
        ]);

        $farmer = Farmer::create([
            'name'   => self::CUSTOMER_NAME,
            'mobile' => self::CUSTOMER_MOBILE,
            'status' => 1,
        ]);
        Sanctum::actingAs($farmer, ['*']);

        $response = $this->postJson($endpoint, array_merge([
            'customer_name'     => self::CUSTOMER_NAME,
            'customer_mobile'   => self::CUSTOMER_MOBILE,
            'hatchery_id'       => $hatchery->id,
            'hatchery_name'     => self::HATCHERY_NAME,
            'category_id'       => $categoryId,
            'unit'              => 'Lakhs',
            'no_of_pieces'      => 5,
            'price'             => 1500,
            'dropping_location' => 'Kavali',
            'packing_date'      => '2026-08-07',
            'delivery_date'     => '2026-08-09',
        ], $overrides));

        return [$response, $vendor];
    }

    /** The exact push the vendor's device receives. */
    private function vendorPush(): array
    {
        $pushes = array_values(array_filter(
            $this->fcm->calls,
            fn ($c) => ($c['data']['type'] ?? null) === 'new_booking'
        ));

        $this->assertCount(1, $pushes, 'expected exactly one new_booking push to the vendor');

        return $pushes[0];
    }

    // ---------------------------------------------------------------- tests

    /** THE requirement: two lines, short id, no customer. */
    public function test_body_is_exactly_short_booking_id_and_hatchery_name(): void
    {
        [$response] = $this->placeBooking();
        $response->assertOk()->assertJsonPath('status', true);

        $bookingId = $response->json('data.booking_id');
        $push = $this->vendorPush();

        $this->assertSame('New Booking received', $push['title']);
        $this->assertSame(
            "booking id : #{$bookingId}\nhatchery name : " . self::HATCHERY_NAME,
            $push['body'],
            'the vendor notification body must be exactly these two lines'
        );
    }

    /** The removal that was asked for. */
    public function test_body_has_no_booked_by_line(): void
    {
        $this->placeBooking();
        $body = $this->vendorPush()['body'];

        $this->assertStringNotContainsStringIgnoringCase('booked by', $body);
        $this->assertCount(2, explode("\n", $body), 'body must be 2 lines, not 3');
    }

    /** The customer's number/name must not reach the vendor's lock screen. */
    public function test_body_never_leaks_the_customer_mobile_or_name(): void
    {
        $this->placeBooking();
        $push = $this->vendorPush();

        $this->assertStringNotContainsString(self::CUSTOMER_MOBILE, $push['body']);
        $this->assertStringNotContainsString(self::CUSTOMER_NAME, $push['body']);
        $this->assertStringNotContainsString(self::CUSTOMER_MOBILE, $push['title']);
        $this->assertStringNotContainsString(self::CUSTOMER_NAME, $push['title']);
    }

    /**
     * With no customer_name the old code fell back to the mobile number.
     * That fallback must be gone too.
     */
    public function test_no_customer_line_even_when_name_is_blank(): void
    {
        [$response] = $this->placeBooking('/api/farmer/book-hatchery', ['customer_name' => '']);
        $response->assertOk();

        $body = $this->vendorPush()['body'];

        $this->assertStringNotContainsString(self::CUSTOMER_MOBILE, $body);
        $this->assertStringNotContainsStringIgnoringCase('booked by', $body);
    }

    /** The long OD-BS-... reference must not be in the visible body. */
    public function test_body_does_not_show_the_long_booking_uid(): void
    {
        $this->placeBooking();
        $push = $this->vendorPush();

        $uid = DB::table('bookings')->value('booking_uid');
        $this->assertNotEmpty($uid, 'sanity: a booking_uid was generated');
        $this->assertStringStartsWith('OD-BS-', $uid);

        $this->assertStringNotContainsString($uid, $push['body']);
        $this->assertDoesNotMatchRegularExpression('/OD-BS-/', $push['body']);
    }

    /** The id shown must be the DB primary key — what the admin panel lists. */
    public function test_id_in_body_is_the_same_id_the_admin_panel_shows(): void
    {
        $this->placeBooking();
        $push = $this->vendorPush();

        $booking = DB::table('bookings')->first();

        // resources/views/admin/bookings/index.blade.php renders #{{ $booking->id }}
        $adminPanelLabel = '#' . $booking->id;

        $this->assertStringContainsString("booking id : {$adminPanelLabel}", $push['body']);
        $this->assertSame((string) $booking->id, (string) $push['data']['booking_id']);
    }

    /** Guards against the blade changing to some other id without this test noticing. */
    public function test_admin_panel_still_renders_the_plain_numeric_id(): void
    {
        $blade = file_get_contents(base_path('resources/views/admin/bookings/index.blade.php'));

        $this->assertStringContainsString('#{{ $booking->id }}', $blade,
            'admin bookings list must still render #<id>; if it changed, the push format must change with it');
    }

    /** Spot bookings go through the same code path and must look the same. */
    public function test_spot_booking_uses_the_same_format(): void
    {
        [$response] = $this->placeBooking('/api/farmer/book-spot-hatchery');
        $response->assertOk()->assertJsonPath('status', true);

        $bookingId = $response->json('data.booking_id');

        $this->assertSame(
            "booking id : #{$bookingId}\nhatchery name : " . self::HATCHERY_NAME,
            $this->vendorPush()['body']
        );
    }

    /** The tap payload is untouched — nothing downstream loses data. */
    public function test_data_payload_still_carries_uid_and_names(): void
    {
        $this->placeBooking();
        $data = $this->vendorPush()['data'];

        $booking = DB::table('bookings')->first();

        $this->assertSame('new_booking', $data['type']);
        $this->assertSame((string) $booking->id, (string) $data['booking_id']);
        $this->assertSame($booking->booking_uid, $data['booking_uid']);
        $this->assertSame(self::HATCHERY_NAME, $data['hatchery_name']);
        $this->assertSame(self::CUSTOMER_NAME, $data['customer_name']);
    }

    /** It goes to the hatchery owner's device, not anyone else's. */
    public function test_push_goes_to_the_owning_vendors_token(): void
    {
        [, $vendor] = $this->placeBooking();

        $this->assertSame($vendor->fcm_token, $this->vendorPush()['token']);
    }

    /** A vendor with no device token must not break the customer's booking. */
    public function test_booking_still_succeeds_when_vendor_has_no_token(): void
    {
        $vendor = Vendor::create(['name' => 'No Token', 'mobile' => '9111111111', 'status' => 1]);
        $location = HatcheryLocation::create(['location_name' => 'Nellore', 'state_code' => 'TS']);
        $hatchery = Hatchery::create([
            'hatchery_name' => self::HATCHERY_NAME,
            'vendor_id'     => $vendor->id,
            'location_id'   => $location->id,
            'is_active'     => true,
        ]);
        $categoryId = DB::table('hatchery_categories')->insertGetId(['category_name' => 'Vannamei']);

        Sanctum::actingAs(Farmer::create(['mobile' => self::CUSTOMER_MOBILE, 'status' => 1]), ['*']);

        $this->postJson('/api/farmer/book-hatchery', [
            'customer_mobile' => self::CUSTOMER_MOBILE,
            'hatchery_id'     => $hatchery->id,
            'hatchery_name'   => self::HATCHERY_NAME,
            'category_id'     => $categoryId,
        ])->assertOk()->assertJsonPath('status', true);

        $this->assertSame([], array_values(array_filter(
            $this->fcm->calls,
            fn ($c) => ($c['data']['type'] ?? null) === 'new_booking'
        )));
        $this->assertSame(1, DB::table('bookings')->count());
    }
}
