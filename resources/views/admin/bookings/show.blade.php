@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">Booking Details</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('bookings.index') }}">Bookings</a></li>
                <li class="breadcrumb-item active">Details</li>
            </ol>
        </nav>
    </div>

    @include('flash_msg')

    @php
        $statusMap = [
            1 => 'Pending',
            2 => 'Confirmed',
            3 => 'Driver Assigned',
            4 => 'In Journey',
            5 => 'Delivered',
            6 => 'Cancelled',
        ];
        $statusColors = [
            1 => 'status-pending',
            2 => 'status-confirmed',
            3 => 'status-assigned',
            4 => 'status-inprogress',
            5 => 'status-completed',
            6 => 'status-cancelled',
        ];
        $bookingTypeMap = [
            0 => 'Hatchery Booking',
            1 => 'Spot Hatchery Booking',
            2 => 'Vehicle Hatchery Booking',
        ];
    @endphp

    {{-- Booking Information Section --}}
    <div class="card mb-4" style="background: #f8f9fa;">
        <div class="card-header bg-primary text-white py-2">
            <strong><i class="fas fa-info-circle mr-2"></i>Booking Information</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Booking ID</label>
                    <p class="font-weight-bold">{{ $booking->id }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Booking UID</label>
                    <p class="font-weight-bold">{{ $booking->booking_uid ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Booking Type</label>
                    <p class="font-weight-bold">{{ $bookingTypeMap[$booking->is_spot] ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Status</label>
                    <p>
                        <span class="badge {{ $statusColors[$booking->status] ?? 'badge-secondary' }}">
                            {{ $statusMap[$booking->status] ?? 'Unknown' }}
                        </span>
                    </p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Category</label>
                    <p class="font-weight-bold">{{ optional($booking->category)->category_name ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Hatchery Name</label>
                    <p class="font-weight-bold">{{ $booking->hatchery_name ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Vendor</label>
                    <p class="font-weight-bold">{{ $booking->vendor->name ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Customer Name</label>
                    <p class="font-weight-bold">{{ $booking->customer_name ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Customer Mobile</label>
                    <p class="font-weight-bold">{{ $booking->customer_mobile ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Number of Pieces</label>
                    <p class="font-weight-bold">{{ $booking->no_of_pieces ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Salinity</label>
                    <p class="font-weight-bold">{{ $booking->salinity ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Unit</label>
                    <p class="font-weight-bold">{{ $booking->unit ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Location & Schedule Section --}}
    <div class="card mb-4" style="background: #f8f9fa;">
        <div class="card-header bg-info text-white py-2">
            <strong><i class="fas fa-map-marker-alt mr-2"></i>Location & Schedule</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Dropping Location</label>
                    <p class="font-weight-bold">{{ $booking->dropping_location ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Preferred Date</label>
                    <p class="font-weight-bold">{{ $booking->packing_date ? $booking->packing_date->format('d M Y') : 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Expected Delivery Date</label>
                    <p class="font-weight-bold">{{ $booking->delivery_datetime ? \Carbon\Carbon::parse($booking->delivery_datetime)->format('d M Y') : ($booking->delivery_date ? \Carbon\Carbon::parse($booking->delivery_date)->format('d M Y') : ($booking->delivery_expected ? \Carbon\Carbon::parse($booking->delivery_expected)->format('d M Y') : 'N/A')) }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Travel Cost</label>
                    <p class="font-weight-bold">{{ $booking->price ? '₹' . number_format($booking->price, 2) : 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Estimated Price</label>
                    <p class="font-weight-bold">{{ $booking->estimated_price ? '₹' . number_format($booking->estimated_price, 2) : 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Descriptions Section --}}
    <div class="card mb-4" style="background: #f8f9fa;">
        <div class="card-header bg-secondary text-white py-2">
            <strong><i class="fas fa-file-alt mr-2"></i>Descriptions</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Booking Status</label>
                    <p class="font-weight-bold">{{ $booking->vendor_booking_description ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Vehicle Status</label>
                    <p class="font-weight-bold">{{ $booking->vendor_vehicle_description ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Driver Details Section --}}
    <div class="card mb-4" style="background: #f8f9fa;">
        <div class="card-header bg-success text-white py-2">
            <strong><i class="fas fa-user mr-2"></i>Driver Details</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Driver Name</label>
                    <p class="font-weight-bold">{{ $booking->driver_name ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Driver Mobile</label>
                    <p class="font-weight-bold">{{ $booking->driver_mobile ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Vehicle Number</label>
                    <p class="font-weight-bold">{{ $booking->vehicle_number ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Vehicle Start Date</label>
                    <p class="font-weight-bold">{{ $booking->vehicle_started_date ? \Carbon\Carbon::parse($booking->vehicle_started_date)->format('d M Y') : 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Vehicle End Date</label>
                    <p class="font-weight-bold">{{ $booking->vehicle_end_date ? \Carbon\Carbon::parse($booking->vehicle_end_date)->format('d M Y') : 'N/A' }}</p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Vehicle Start Location</label>
                    <p class="font-weight-bold">{{ $booking->vehicle_start_address ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Update Status Section --}}
    <div class="card mb-4" style="background: #f8f9fa;">
        <div class="card-header bg-warning text-dark py-2">
            <strong><i class="fas fa-sync-alt mr-2"></i>Update Booking Status</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.bookings.updateStatus', $booking->id) }}" id="statusForm">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <select name="status" id="status_select" class="form-control">
                            <option value="1" {{ $booking->status == 1 ? 'selected' : '' }}>Pending</option>
                            <option value="2" {{ $booking->status == 2 ? 'selected' : '' }}>Confirmed</option>
                            <option value="3" {{ $booking->status == 3 ? 'selected' : '' }}>Driver Assigned</option>
                            <option value="4" {{ $booking->status == 4 ? 'selected' : '' }}>In Journey</option>
                            <option value="5" {{ $booking->status == 5 ? 'selected' : '' }}>Delivered</option>
                            <option value="6" {{ $booking->status == 6 ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                </div>

                {{-- Cancellation Reason --}}
                <div class="row mt-3" id="cancellation_reason_section" style="display: none;">
                    <div class="col-md-6">
                        <label class="form-label text-danger">Cancellation Reason <span class="text-danger">*</span></label>
                        <select name="reason_code" id="reason_code" class="form-control">
                            <option value="">Select reason</option>
                            <option value="1">Delay in processing</option>
                            <option value="2">Incorrect order details</option>
                            <option value="3">Wrong quantity requested</option>
                            <option value="4">Stock quality issues</option>
                            <option value="5">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="other_reason_section" style="display: none;">
                        <label class="form-label text-danger">Specify Reason <span class="text-danger">*</span></label>
                        <textarea name="other_reason" id="other_reason" class="form-control" rows="2" maxlength="500" placeholder="Enter the reason shown to the customer">{{ old('other_reason', $booking->cancellation_reason == 5 ? $booking->cancellation_reason_text : '') }}</textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3" id="update_status_btn">Update Status</button>
            </form>
        </div>
    </div>

    {{-- Assign Driver Section --}}
    <div class="card mb-4" style="background: #f8f9fa;">
        <div class="card-header bg-dark text-white py-2">
            <strong><i class="fas fa-truck mr-2"></i>Assign Driver</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.bookings.assignDriver', $booking->id) }}" id="driverForm">
                @csrf
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Select Driver</label>
                        <select name="driver_id" id="driver_select" class="form-control select2-driver">
                            <option value="">Select Driver</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}"
                                    data-name="{{ $driver->name }}"
                                    data-mobile="{{ $driver->mobile }}"
                                    {{ $booking->driver_id == $driver->id ? 'selected' : '' }}>
                                    {{ $driver->name }} - {{ $driver->mobile }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <input type="hidden" name="driver_name" id="driver_name_hidden" value="{{ $booking->driver_name }}">
                <input type="hidden" name="driver_mobile" id="driver_mobile_hidden" value="{{ $booking->driver_mobile }}">

                <button type="submit" class="btn btn-success">Assign Driver</button>
            </form>
        </div>
    </div>

    {{-- Cancellation Details --}}
    @if($booking->status == 6)
    <div class="card mb-4" style="background: #f8f9fa;">
        <div class="card-header bg-danger text-white py-2">
            <strong><i class="fas fa-times-circle mr-2"></i>Cancellation Details</strong>
        </div>
        <div class="card-body">
            @php
                $reasonMap = [
                    1 => 'Delay in processing',
                    2 => 'Incorrect order details',
                    3 => 'Wrong quantity requested',
                    4 => 'Stock quality issues',
                    5 => 'Other'
                ];
            @endphp
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Reason</label>
                    <p class="font-weight-bold text-danger">{{ $reasonMap[$booking->cancellation_reason] ?? 'N/A' }}</p>
                    @if($booking->cancellation_reason == 5 && $booking->cancellation_reason_text)
                        <label class="form-label text-muted mt-2">Reason Note</label>
                        <p class="font-weight-bold text-dark">{{ $booking->cancellation_reason_text }}</p>
                    @endif
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted">Cancelled At</label>
                    <p class="font-weight-bold">{{ $booking->updated_at->format('d M Y, h:i A') }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Customer Rating — shown when rated, or delivered & not yet dismissed.
         Hidden entirely once the customer dismisses the rating prompt. --}}
    @if($booking->rating || ($booking->status == 5 && !$booking->rating_dismissed_at))
    <div class="card mt-3">
        <div class="card-body">
            <strong><i class="fas fa-star mr-2" style="color: #f5a623;"></i>Customer Rating</strong>
            <hr>
            @if($booking->rating)
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label text-muted">Rating</label>
                    <div style="font-size: 20px; color: #f5a623;">
                        @for($i = 1; $i <= 5; $i++)
                            <i class="fas fa-star" style="{{ $i <= $booking->rating->rating ? 'color: #f5a623;' : 'color: #ddd;' }}"></i>
                        @endfor
                        <span class="ml-2" style="font-size: 16px; color: #333;">{{ $booking->rating->rating }}/5</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted">Message</label>
                    <p class="font-weight-bold">{{ $booking->rating->message ?? 'No message' }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted">Rated On</label>
                    <p class="font-weight-bold">{{ $booking->rating->created_at->format('d M Y, h:i A') }}</p>
                </div>
            </div>
            @else
                <p class="text-muted mb-0"><i class="far fa-clock mr-1"></i> Not rated yet.</p>
            @endif
        </div>
    </div>
    @endif

    {{-- Action Buttons --}}
    <div class="d-flex gap-2 mb-4">
        <a href="{{ route('bookings.edit', $booking->id) }}" class="btn btn-primary">
            <i class="fas fa-edit mr-1"></i> Edit Booking
        </a>
        <a href="{{ route('bookings.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single {
        height: 40px !important;
        display: flex;
        align-items: center;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: normal !important;
        padding-left: 12px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 100%;
        display: flex;
        align-items: center;
    }
    .gap-2 {
        gap: 0.5rem;
    }
    .badge.status-pending {
        background-color: #FFC107;
        color: #000;
    }
    .badge.status-confirmed {
        background-color: #6f42c1;
        color: #fff;
    }
    .badge.status-assigned {
        background-color: #20c997;
        color: #fff;
    }
    .badge.status-inprogress {
        background-color: #007bff;
        color: #fff;
    }
    .badge.status-completed {
        background-color: #28a745;
        color: #fff;
    }
    .badge.status-cancelled {
        background-color: #dc3545;
        color: #fff;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2 for driver dropdown
    $('.select2-driver').select2({
        placeholder: "Search driver by name or mobile",
        allowClear: true,
        width: '100%'
    });

    // When driver is selected, update hidden fields
    $('#driver_select').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var driverName = selectedOption.data('name') || '';
        var driverMobile = selectedOption.data('mobile') || '';

        $('#driver_name_hidden').val(driverName);
        $('#driver_mobile_hidden').val(driverMobile);
    });

    // Show/hide cancellation reason based on status selection
    function toggleCancellationReason() {
        var selectedStatus = $('#status_select').val();
        if (selectedStatus == '6') {
            $('#cancellation_reason_section').slideDown();
            $('#reason_code').prop('required', true);
        } else {
            $('#cancellation_reason_section').slideUp();
            $('#reason_code').prop('required', false);
        }
    }

    // Show/hide the free-text "Other" reason field
    function toggleOtherReason() {
        if ($('#reason_code').val() == '5') {
            $('#other_reason_section').slideDown();
        } else {
            $('#other_reason_section').slideUp();
        }
    }

    // Initial check
    toggleCancellationReason();
    toggleOtherReason();

    // On status change
    $('#status_select').on('change', function() {
        toggleCancellationReason();
    });

    // On reason change
    $('#reason_code').on('change', function() {
        toggleOtherReason();
    });

    // Handle status form submission
    $('#statusForm').on('submit', function(e) {
        var selectedStatus = $('#status_select').val();

        // If cancelling, validate reason
        if (selectedStatus == '6') {
            var reasonCode = $('#reason_code').val();
            if (!reasonCode) {
                e.preventDefault();
                alert('Please select a cancellation reason');
                return false;
            }

            // "Other" requires the free-text note
            if (reasonCode == '5' && !$('#other_reason').val().trim()) {
                e.preventDefault();
                alert('Please specify the cancellation reason');
                return false;
            }

            // Change form action to cancel route
            $(this).attr('action', '{{ route("admin.bookings.cancel", $booking->id) }}');
        } else {
            // Reset to update status route
            $(this).attr('action', '{{ route("admin.bookings.updateStatus", $booking->id) }}');
        }
    });
});
</script>
@endpush
