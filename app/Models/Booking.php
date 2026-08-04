<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_uid',
        'vendor_id',
        'hatchery_id',         // link to hatcheries.id
        'brand_id',
        'category_id',
        'price',
        'location_id',
        'farmer_id',
        'booking_by',

        'customer_id',
        'customer_name',
        'customer_mobile',
        'delivery_location',
        'hatchery_name',
        'hatchery_location',
        'unit',
        'no_of_pieces',
        'salinity',
        'estimated_price',
        'dropping_location',
        'packing_date',
        'delivery_date',
        'delivery_datetime',
        'vendor_booking_description',
        'vendor_vehicle_description',
        'categories',
        'count',
        'available_space',
        'vehicle_images',
        'driver_id',
        'driver_name',
        'driver_mobile',
        'vehicle_number',
        'vehicle_started_date',
        'vehicle_end_date',
        'is_spot',

        // Driver location fields
        'driver_lat',
        'driver_lng',
        'driver_location_name',
        'driver_image',
        'pickup_lat',
        'pickup_lng',
        'drop_lat',
        'drop_lng',
        'delivery_expected',
        'delivery_note',
        'status',
        'priority',
        'cancellation_reason',
        'cancellation_reason_text',

        // Vehicle start location fields
        'vehicle_start_lat',
        'vehicle_start_lng',
        'vehicle_start_address',

        // Status timestamps
        'confirmed_at',
        'driver_assigned_at',
        'in_progress_at',
        'delivered_at',
        'rating_dismissed_at',
        'cancelled_at',
        'approaching_notified_at',
        'driver_location_updated_at',
        'driver_inactive_reason',

        // Persisted road-snapped travelled-path (snap-on-write, see
        // addTrackingPoint::maybeRefreshTrackingPath). Avoids re-running
        // Roads API on every customer poll.
        'tracking_path',
        'tracking_path_last_id',
        'tracking_path_at',
    ];

    // ✅ Cast JSON fields
    protected $casts = [
        'vehicle_images' => 'array',
        'categories' => 'array',
        'packing_date' => 'date',
        'delivery_date' => 'date',
        'vehicle_started_date' => 'datetime',
        'vehicle_end_date' => 'datetime',
        'delivery_datetime' => 'datetime',
        'confirmed_at' => 'datetime',
        'driver_assigned_at' => 'datetime',
        'in_progress_at' => 'datetime',
        'delivered_at' => 'datetime',
        'rating_dismissed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'stock_applied_at' => 'datetime',
        'approaching_notified_at' => 'datetime',
        'driver_location_updated_at' => 'datetime',
        'tracking_path' => 'array',
        'tracking_path_at' => 'datetime',
    ];

    /**
     * status code => the column that records WHEN the booking last entered it.
     *
     * 1 = Pending (no timestamp), 2 = Confirmed, 3 = Driver Assigned,
     * 4 = In Journey, 5 = Delivered, 6 = Cancelled.
     */
    public const STATUS_CONFIRMED = 2;
    public const STATUS_CANCELLED = 6;

    public const STATUS_TIMESTAMPS = [
        2 => 'confirmed_at',
        3 => 'driver_assigned_at',
        4 => 'in_progress_at',
        5 => 'delivered_at',
        6 => 'cancelled_at',
    ];

    /**
     * Keep the status timestamps on the LATEST transition, not the first one.
     *
     * These columns were previously stamped write-once — every call site guarded
     * with `?? now()`, `if (!$booking->$column)` or `if (empty($driver_id))`. So a
     * booking whose driver was assigned, removed, then re-assigned kept the
     * ORIGINAL assignment time and the customer's timeline showed a stale date.
     *
     * Centralising it here (rather than in each controller) means every write
     * path is covered — admin panel, vendor API and driver API all mutate
     * bookings through Eloquent model instances, and nothing in the codebase
     * uses a query-builder update that would bypass these events.
     *
     * A call site that sets the column itself still wins: the `isDirty($column)`
     * check leaves an explicit value alone.
     */
    protected static function booted(): void
    {
        static::creating(function (self $booking) {
            $column = self::STATUS_TIMESTAMPS[(int) $booking->status] ?? null;
            if ($column && empty($booking->{$column})) {
                $booking->{$column} = now();
            }
        });

        static::updating(function (self $booking) {
            // Entering a status — including RE-entering one — refreshes its stamp.
            if ($booking->isDirty('status')) {
                $column = self::STATUS_TIMESTAMPS[(int) $booking->status] ?? null;
                if ($column && ! $booking->isDirty($column)) {
                    $booking->{$column} = now();
                }
            }

            // A driver swap can happen while the status stays 3, so the status
            // check above would miss it. Any change of driver_id to a real
            // driver is a fresh assignment and must re-stamp.
            if (
                $booking->isDirty('driver_id')
                && ! empty($booking->driver_id)
                && ! $booking->isDirty('driver_assigned_at')
            ) {
                $booking->driver_assigned_at = now();
            }
        });

        // Spot-hatchery stock follows the same "centralise it in the model"
        // reasoning as the timestamps above: bookings are confirmed from the
        // vendor API and the admin panel, and cancelled from admin, vendor AND
        // the customer app. Hooking the model covers every one of those paths.
        static::created(function (self $booking) {
            $booking->syncSpotHatcheryStock();
        });

        static::updated(function (self $booking) {
            if ($booking->wasChanged('status')) {
                $booking->syncSpotHatcheryStock();
            }
        });
    }

    /**
     * Keep the spot hatchery's available pieces in step with this booking.
     *
     * Confirmed (2) → subtract this booking's pieces from the hatchery.
     * Cancelled (6) → add them back, so the stock is available again.
     *
     * `stock_applied_at` records whether this booking is CURRENTLY holding
     * stock, which makes the operation idempotent: re-saving a confirmed
     * booking never double-subtracts, cancelling twice never double-credits,
     * and a booking that is cancelled and later re-confirmed deducts again.
     *
     * Only spot hatcheries (`hatcheries.is_spot = 1`) are tracked — regular
     * hatchery and vehicle bookings leave stock alone.
     */
    public function syncSpotHatcheryStock(): void
    {
        if (empty($this->hatchery_id)) {
            return;
        }

        $pieces = (int) $this->no_of_pieces;
        if ($pieces <= 0) {
            return;
        }

        $status = (int) $this->status;
        $holdsStock = !is_null($this->stock_applied_at);

        if ($status === self::STATUS_CONFIRMED && !$holdsStock) {
            $delta = -$pieces;
        } elseif ($status === self::STATUS_CANCELLED && $holdsStock) {
            $delta = $pieces;
        } else {
            return; // Nothing to do for this transition.
        }

        DB::transaction(function () use ($delta, $pieces) {
            // Lock the row so two bookings confirmed at the same moment can't
            // both read the old count and write back a stale number.
            $hatchery = Hatchery::where('id', $this->hatchery_id)
                ->where('is_spot', 1)
                ->lockForUpdate()
                ->first();

            if (!$hatchery) {
                return; // Not a spot hatchery — stock isn't tracked here.
            }

            $current = (int) $hatchery->no_of_pieces;
            $updated = $current + $delta;

            if ($updated < 0) {
                // The app blocks over-booking before it gets here, so this means
                // stock was edited down after the booking was placed. Floor at
                // zero rather than showing a negative count to customers.
                Log::warning('Spot hatchery stock would go negative', [
                    'booking_id' => $this->id,
                    'hatchery_id' => $hatchery->id,
                    'available' => $current,
                    'requested' => $pieces,
                ]);
                $updated = 0;
            }

            $hatchery->forceFill(['no_of_pieces' => $updated])->saveQuietly();

            // Flip the guard. Written with the query builder so this does not
            // re-enter the model events above.
            $appliedAt = $delta < 0 ? now() : null;
            DB::table('bookings')->where('id', $this->id)->update([
                'stock_applied_at' => $appliedAt,
            ]);
            $this->setAttribute('stock_applied_at', $appliedAt);
            $this->syncOriginalAttribute('stock_applied_at');
        });
    }

    // ✅ Relationship with Vendor
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function farmer()
    {
        // return $this->belongsTo(Farmer::class);
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    public function seed()
    {
        // return $this->belongsTo(Seed::class);
        return $this->belongsTo(Seed::class, 'seed_id');
    }


    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function category()
    {
        return $this->belongsTo(HatcheryCategory::class, 'category_id');
    }

    public function location()
    {
        return $this->belongsTo(HatcheryLocation::class, 'location_id');
    }


    public function driverByMobile()
    {
        return $this->belongsTo(Driver::class, 'driver_mobile', 'mobile');
    }

    public function hatchery()
    {
        // return $this->belongsTo(Hatchery::class, 'hatchery_name', 'hatchery_name');
        return $this->belongsTo(Hatchery::class, 'hatchery_id', 'id');
    }

    public function vehicleAvailability()
    {
        return $this->belongsTo(VehicleAvailability::class, 'hatchery_id', 'id');
    }

    public function trackings()
    {
        return $this->hasMany(VehicleTracking::class, 'booking_id')->orderBy('order');
    }

    public function rating()
    {
        return $this->hasOne(BookingRating::class);
    }


    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }


}
