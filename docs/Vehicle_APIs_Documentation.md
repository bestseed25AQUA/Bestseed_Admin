# Vehicle APIs Documentation

**Base URL:** `/api/farmer`
**Authentication:** All APIs require `Bearer Token` (Sanctum)
**Last Updated:** December 5, 2025

---

## Table of Contents
1. [Vehicle List (Tracking)](#1-vehicle-list-tracking)
2. [Vehicle Available Booking](#2-vehicle-available-booking)
3. [Vehicle Booking Detail](#3-vehicle-booking-detail)
4. [Vehicle Booking Delete](#4-vehicle-booking-delete)
5. [Vehicle Tracking](#5-vehicle-tracking)
6. [Update Driver Location](#6-update-driver-location)
7. [Add Tracking Point](#7-add-tracking-point)

---

## 1. Vehicle List (Tracking)

Fetch vehicle tracking list for the logged-in farmer with filter options.

### Endpoint
```
GET /api/farmer/vehicle_list
```

### Query Parameters
| Parameter | Type   | Required | Description                          |
|-----------|--------|----------|--------------------------------------|
| month     | string | No       | Filter by month (e.g., "02")         |
| year      | string | No       | Filter by year (e.g., "2025")        |
| date      | string | No       | Filter by exact date (e.g., "2025-02-15") |

### Example Requests
```
GET /api/farmer/vehicle_list
GET /api/farmer/vehicle_list?month=02
GET /api/farmer/vehicle_list?month=02&year=2025
GET /api/farmer/vehicle_list?date=2025-02-15
```

### Success Response (200)
```json
{
  "status": true,
  "message": "Tracking data fetched successfully",
  "vehicles": [
    {
      "hatchery_name": "Super Hatchery Farm",
      "category_name": "Broiler Chick",
      "images": [
        "https://images.pexels.com/photos/112460/pexels-photo-112460.jpeg",
        "https://images.pexels.com/photos/2199293/pexels-photo-2199293.jpeg"
      ],
      "customer": {
        "name": "Vicky Patel",
        "mobile": "852885555"
      },
      "booking_details": {
        "id": 101,
        "pieces": "5000",
        "unit_name": "PCS",
        "available_date": "2025-02-15"
      },
      "driver_details": {
        "driver_name": "Ramesh Kumar",
        "driver_mobile": "9876543210",
        "vehicle_number": "GJ05AB1234"
      },
      "sms_to": "9876543210"
    }
  ]
}
```

### Error Response (401)
```json
{
  "status": false,
  "message": "Unauthorized. Please login."
}
```

---

## 2. Vehicle Available Booking

Fetch all vehicle availability bookings for the logged-in farmer.

### Endpoint
```
GET /api/farmer/vehicle_available_booking
```

### Success Response (200)
```json
{
  "success": true,
  "message": "Vehicle availability bookings fetched successfully",
  "data": [
    {
      "id": "1",
      "booking_id": "324646",
      "time": "12:30 PM",
      "date": "25/11/2025",
      "hatchery_name": "Seven Star Hatchery",
      "category_name": "Syaqua",
      "status": "Pending",
      "pickup_location": "Seven Star Hatchery",
      "drop_location": "Kakinada, Andhra Pradesh",
      "quantity": "1400 Pieces"
    },
    {
      "id": "2",
      "booking_id": "324647",
      "time": "02:45 PM",
      "date": "26/11/2025",
      "hatchery_name": "Premium Hatchery",
      "category_name": "Layer Chicks",
      "status": "Confirmed",
      "pickup_location": "Premium Hatchery",
      "drop_location": "Vijayawada, Andhra Pradesh",
      "quantity": "2000 Pieces"
    }
  ]
}
```

### Status Values
| Status          | Description                    |
|-----------------|--------------------------------|
| Pending         | Booking awaiting confirmation  |
| Confirmed       | Booking confirmed              |
| Driver_assigned | Driver has been assigned       |
| In_progress     | Delivery in progress           |
| Delivered       | Order delivered                |
| Cancelled       | Booking cancelled              |

---

## 3. Vehicle Booking Detail

Fetch detailed information about a specific vehicle booking.

### Endpoint
```
GET /api/farmer/vehicle_booking_detail/{id}
```

### Path Parameters
| Parameter | Type    | Required | Description     |
|-----------|---------|----------|-----------------|
| id        | integer | Yes      | Booking ID      |

### Example Request
```
GET /api/farmer/vehicle_booking_detail/1
```

### Success Response (200)
```json
{
  "success": true,
  "message": "Booking details fetched successfully",
  "data": {
    "booking_id": 1,
    "vehicle_id": 14,
    "driver_id": 52,
    "is_vehicle_assigned": true,
    "status": "Confirmed",
    "status_description": "We'll notify you when the driver assigned and start their journey",
    "pickup_details": {
      "location": "Seven Star Hatchery",
      "date": "24/11/2025",
      "time": "10:20 AM"
    },
    "drop_details": {
      "location": "Kakinada, Andhra Pradesh",
      "date": "26/11/2025",
      "time": "01:20 PM"
    },
    "vehicle_booking_details": {
      "hatchery_name": "Rama Hatchery",
      "brand_type": "Syaqua",
      "seed_qty": "2000 Pieces",
      "booking_date": "23/11/2025",
      "booking_time": "12:30 PM"
    },
    "vehicle_booking_status": [
      {
        "title": "confirm",
        "date": "Mon, 21-11-2025",
        "time": "12:23 PM"
      },
      {
        "title": "driver_assigned",
        "date": "Mon, 21-11-2025",
        "time": "12:23 PM"
      },
      {
        "title": "in_progress",
        "date": "Mon, 21-11-2025",
        "time": "12:23 PM"
      },
      {
        "title": "delivered",
        "date": "Mon, 21-11-2025",
        "time": "12:23 PM"
      }
    ]
  }
}
```

### Notes
- `vehicle_booking_status` array is only included when status is **Confirmed**, **Driver_assigned**, **In_progress**, or **Delivered**
- For **Pending** status, `vehicle_booking_status` will not be present in response

### Error Response (404)
```json
{
  "success": false,
  "message": "Booking not found or access denied."
}
```

---

## 4. Vehicle Booking Delete

Delete a vehicle booking.

### Endpoint
```
GET /api/farmer/vehicle_booking_delete/{id}
```

### Path Parameters
| Parameter | Type    | Required | Description     |
|-----------|---------|----------|-----------------|
| id        | integer | Yes      | Booking ID      |

### Example Request
```
GET /api/farmer/vehicle_booking_delete/1
```

### Success Response (200)
```json
{
  "success": true,
  "message": "Booking deleted successfully."
}
```

### Error Response (400) - Cannot Delete
```json
{
  "success": false,
  "message": "Cannot delete booking. Booking is already in_progress."
}
```

### Error Response (404) - Not Found
```json
{
  "success": false,
  "message": "Booking not found or access denied."
}
```

### Deletion Rules
| Status          | Can Delete? |
|-----------------|-------------|
| Pending         | Yes         |
| Confirmed       | Yes         |
| Driver_assigned | Yes         |
| In_progress     | No          |
| Delivered       | No          |

---

## 5. Vehicle Tracking

Fetch real-time vehicle tracking details for a specific booking.

### Endpoint
```
GET /api/farmer/vehicle_tracking/{id}
```

### Path Parameters
| Parameter | Type    | Required | Description     |
|-----------|---------|----------|-----------------|
| id        | integer | Yes      | Booking ID      |

### Example Request
```
GET /api/farmer/vehicle_tracking/1
```

### Success Response (200)
```json
{
  "status": true,
  "message": "Tracking data fetched successfully",
  "data": {
    "vehicle_id": 14,
    "booking_id": 1,
    "pickup": {
      "name": "Seven Star Hatchery",
      "lat": 17.3850,
      "lng": 78.4867
    },
    "drop": {
      "name": "Amalapuram",
      "lat": 16.5775,
      "lng": 82.0061
    },
    "driver_location": {
      "name": "Near Kakinada",
      "lat": 16.9891,
      "lng": 82.2475
    },
    "driver_details": {
      "driver_name": "Ramesh",
      "driver_phone": "+919876543210",
      "vehicle_number": "TSN05656",
      "driver_image": "https://example.com/profile.png"
    },
    "delivery_updates": {
      "delivery_expected": "27/06/2025",
      "note": "We've received your booking. Within a few days, we will assign your vehicle"
    },
    "timeline": [
      {
        "title": "Pickup started from",
        "subtitle": "Seven Star Hatchery",
        "time": "2:30 PM",
        "date": "24/06/2025",
        "status": "completed"
      },
      {
        "title": "Kakinada",
        "subtitle": "",
        "time": "10:30 PM",
        "date": "24/06/2025",
        "status": "completed"
      },
      {
        "title": "Vizag",
        "subtitle": "",
        "time": "-",
        "date": "24/06/2025",
        "status": "pending"
      },
      {
        "title": "Vijayawada",
        "subtitle": "",
        "time": "-",
        "date": "",
        "status": "pending"
      },
      {
        "title": "Destination",
        "subtitle": "Amalapuram",
        "time": "-",
        "date": "",
        "status": "pending"
      }
    ]
  }
}
```

### Timeline Status Values
| Status    | Description                     |
|-----------|---------------------------------|
| completed | Location has been reached       |
| current   | Driver is currently here        |
| pending   | Location not yet reached        |

---

## 6. Update Driver Location

Update the driver's current GPS location. This API should be called periodically by the driver's app.

### Endpoint
```
POST /api/farmer/update_driver_location
```

### Request Body
| Field         | Type   | Required | Description                      |
|---------------|--------|----------|----------------------------------|
| booking_id    | integer| Yes      | Booking ID                       |
| lat           | float  | Yes      | Latitude (e.g., 16.9891)         |
| lng           | float  | Yes      | Longitude (e.g., 82.2475)        |
| location_name | string | No       | Human-readable location name     |

### Example Request
```json
{
  "booking_id": 1,
  "lat": 16.9891,
  "lng": 82.2475,
  "location_name": "Near Kakinada"
}
```

### Success Response (200)
```json
{
  "status": true,
  "message": "Driver location updated successfully.",
  "data": {
    "booking_id": 1,
    "driver_lat": "16.9891000",
    "driver_lng": "82.2475000",
    "driver_location_name": "Near Kakinada",
    "updated_at": "2025-12-05 10:30:00"
  }
}
```

### Error Response (404)
```json
{
  "status": false,
  "message": "Booking not found."
}
```

### Error Response (422) - Validation Error
```json
{
  "message": "The booking_id field is required.",
  "errors": {
    "booking_id": ["The booking_id field is required."]
  }
}
```

---

## 7. Add Tracking Point

Add a new checkpoint/milestone to the delivery timeline. This creates a permanent record in the tracking history.

### Endpoint
```
POST /api/farmer/add_tracking_point
```

### Request Body
| Field         | Type   | Required | Description                                    |
|---------------|--------|----------|------------------------------------------------|
| booking_id    | integer| Yes      | Booking ID                                     |
| location_name | string | Yes      | Name of the location                           |
| lat           | float  | No       | Latitude                                       |
| lng           | float  | No       | Longitude                                      |
| title         | string | No       | Display title (defaults to location_name)      |
| subtitle      | string | No       | Additional description                         |
| status        | string | No       | Status: completed, current, pending (default: current) |

### Example Request
```json
{
  "booking_id": 1,
  "location_name": "Kakinada",
  "lat": 16.9891,
  "lng": 82.2475,
  "title": "Kakinada",
  "subtitle": "Reached checkpoint",
  "status": "completed"
}
```

### Success Response (200)
```json
{
  "status": true,
  "message": "Tracking point added successfully.",
  "data": {
    "id": 5,
    "booking_id": 1,
    "location_name": "Kakinada",
    "lat": "16.9891000",
    "lng": "82.2475000",
    "status": "completed",
    "title": "Kakinada",
    "subtitle": "Reached checkpoint",
    "reached_at": "2025-12-05T10:30:00.000000Z",
    "order": 3,
    "created_at": "2025-12-05T10:30:00.000000Z",
    "updated_at": "2025-12-05T10:30:00.000000Z"
  }
}
```

### Notes
- When a new tracking point is added with status `current`, all previous `current` points are automatically marked as `completed`
- Driver's current location is also updated automatically when adding a tracking point

---

## Database Schema

### New Table: `vehicle_trackings`

| Column        | Type           | Description                           |
|---------------|----------------|---------------------------------------|
| id            | BIGINT (PK)    | Auto-increment primary key            |
| booking_id    | BIGINT (FK)    | Foreign key to bookings table         |
| location_name | VARCHAR(255)   | Name of location                      |
| lat           | DECIMAL(10,7)  | Latitude coordinate                   |
| lng           | DECIMAL(10,7)  | Longitude coordinate                  |
| status        | ENUM           | completed, current, pending           |
| title         | VARCHAR(255)   | Display title                         |
| subtitle      | VARCHAR(255)   | Additional description                |
| reached_at    | TIMESTAMP      | When driver reached this location     |
| order         | INT            | Order of timeline points              |
| created_at    | TIMESTAMP      | Record creation time                  |
| updated_at    | TIMESTAMP      | Record update time                    |

### New Fields in `bookings` Table

| Column              | Type           | Description                     |
|---------------------|----------------|---------------------------------|
| driver_lat          | DECIMAL(10,7)  | Driver's current latitude       |
| driver_lng          | DECIMAL(10,7)  | Driver's current longitude      |
| driver_location_name| VARCHAR(255)   | Driver's current location name  |
| driver_image        | VARCHAR(255)   | Driver's profile image URL      |
| pickup_lat          | DECIMAL(10,7)  | Pickup point latitude           |
| pickup_lng          | DECIMAL(10,7)  | Pickup point longitude          |
| drop_lat            | DECIMAL(10,7)  | Drop point latitude             |
| drop_lng            | DECIMAL(10,7)  | Drop point longitude            |
| delivery_expected   | DATE           | Expected delivery date          |
| delivery_note       | TEXT           | Delivery status note            |

---

## Migration Command

Run the following command to create the new table and add fields:

```bash
php artisan migrate
```

---

## Authentication

All APIs require Bearer Token authentication using Laravel Sanctum.

### Header
```
Authorization: Bearer {your_token}
```

### Getting Token
Use the login API to get the authentication token:
```
POST /api/farmer/login
```

---

## Error Codes

| HTTP Code | Description                              |
|-----------|------------------------------------------|
| 200       | Success                                  |
| 400       | Bad Request (validation or business logic error) |
| 401       | Unauthorized (invalid or missing token)  |
| 404       | Resource not found                       |
| 422       | Validation error                         |
| 500       | Server error                             |

---

## Contact

For any queries regarding these APIs, please contact the development team.
