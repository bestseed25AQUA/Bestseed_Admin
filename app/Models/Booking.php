<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'approaching_notified_at' => 'datetime',
        'driver_location_updated_at' => 'datetime',
        'tracking_path' => 'array',
        'tracking_path_at' => 'datetime',
    ];

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
