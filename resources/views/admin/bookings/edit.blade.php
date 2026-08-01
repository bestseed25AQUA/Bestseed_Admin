@extends('admin.layouts.main')

@section('page_title', 'Edit Booking')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Edit Booking</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('bookings.index') }}">Bookings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Update Booking #{{ $booking->id }}</h4>
                        @include('flash_msg')

                        <form method="POST" action="{{ route('bookings.update', $booking->id) }}" id="editBookingForm">
                            @csrf
                            @method('PUT')

                            <input type="hidden" name="customer_id" value="{{ $booking->customer_id }}">
                            <input type="hidden" name="hatchery_name" id="hatchery_name_hidden" value="{{ $booking->hatchery_name }}">
                            <input type="hidden" name="vendor_id" id="vendor_id_hidden" value="{{ $booking->vendor_id }}">

                            {{-- Booking Information Section --}}
                            <div class="card mb-4" style="background: #f8f9fa;">
                                <div class="card-header bg-primary text-white py-2">
                                    <strong><i class="fas fa-info-circle mr-2"></i>Booking Information</strong>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        {{-- Booking Type --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Booking Type <span class="text-danger">*</span></label>
                                            <select name="is_spot" id="booking_type" class="form-control" required>
                                                <option value="">Choose Booking Type</option>
                                                <option value="0" {{ old('is_spot', $booking->is_spot) == '0' ? 'selected' : '' }}>Hatchery</option>
                                                <option value="1" {{ old('is_spot', $booking->is_spot) == '1' ? 'selected' : '' }}>Spot Hatchery</option>
                                                <option value="2" {{ old('is_spot', $booking->is_spot) == '2' ? 'selected' : '' }}>Vehicle Availability</option>
                                            </select>
                                            @error('is_spot')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Selected Source --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Select Source <span class="text-danger">*</span></label>
                                            <select name="hatchery_id" id="source_select" class="form-control select2" required>
                                                <option value="">Select Source</option>
                                            </select>
                                            @error('hatchery_id')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Booked Category (Editable Dropdown) --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Booked Category <span class="text-danger">*</span></label>
                                            <select name="category_id" class="form-control">
                                                <option value="">Select Category</option>
                                                @foreach ($allcategories as $category)
                                                    <option value="{{ $category->id }}"
                                                        {{ old('category_id', $booking->category_id) == $category->id ? 'selected' : '' }}>
                                                        {{ $category->category_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('category_id')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Select Customer — auto-fills name & mobile below.
                                             Purely a helper; the name/mobile fields stay editable
                                             so you can also type a new customer manually. --}}
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Select Customer (optional)</label>
                                            <select id="customer_select" class="form-control select2-customer">
                                                <option value="">-- Search &amp; select an existing customer --</option>
                                                @foreach ($customers as $customer)
                                                    @php
                                                        $fullName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                                                    @endphp
                                                    <option value="{{ $customer->id }}"
                                                        data-name="{{ $fullName }}"
                                                        data-mobile="{{ $customer->mobile }}">
                                                        {{ $fullName !== '' ? $fullName : 'Customer' }} - {{ $customer->mobile }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">Pick a customer to auto-fill the name &amp; mobile, or type them manually below.</small>
                                        </div>

                                        {{-- Customer Name --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Customer Name</label>
                                            <input type="text" name="customer_name" id="customer_name_input" class="form-control"
                                                value="{{ old('customer_name', $booking->customer_name) }}">
                                            @error('customer_name')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Customer Mobile --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Customer Mobile <span class="text-danger">*</span></label>
                                            <input type="text" name="customer_mobile" id="customer_mobile_input" class="form-control"
                                                value="{{ old('customer_mobile', $booking->customer_mobile) }}" required>
                                            @error('customer_mobile')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Number of Pieces --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Number of Pieces </label>
                                            <input type="number" name="no_of_pieces" id="no_of_pieces" class="form-control"
                                                value="{{ old('no_of_pieces', $booking->no_of_pieces) }}" placeholder="Enter number of pieces">
                                            @error('no_of_pieces')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Salinity --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Salinity</label>
                                            <select name="salinity" class="form-control">
                                                <option value="">Select Salinity</option>
                                                @for ($i = 0; $i <= 40; $i++)
                                                    <option value="{{ $i }}" {{ old('salinity', $booking->salinity) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                                @endfor
                                            </select>
                                            @error('salinity')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Unit --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Unit</label>
                                            <input type="text" name="unit" class="form-control"
                                                value="{{ old('unit', $booking->unit) }}" placeholder="Enter unit/location">
                                            @error('unit')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
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
                                        {{-- Dropping Location with Google Maps --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Dropping Location</label>
                                            <div class="input-group">
                                                <input type="text" name="dropping_location" id="dropping_location" class="form-control"
                                                    value="{{ old('dropping_location', $booking->dropping_location) }}" placeholder="Enter or select location">
                                                <button type="button" class="btn btn-outline-secondary" id="map_picker_btn" title="Pick from map">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </button>
                                            </div>
                                            <input type="hidden" name="drop_lat" id="drop_lat" value="{{ old('drop_lat', $booking->drop_lat) }}">
                                            <input type="hidden" name="drop_lng" id="drop_lng" value="{{ old('drop_lng', $booking->drop_lng) }}">
                                            @error('dropping_location')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Preferred Date --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Preferred Date</label>
                                            <input type="date" name="packing_date" class="form-control"
                                                value="{{ old('packing_date', optional($booking->packing_date)->format('Y-m-d')) }}">
                                            @error('packing_date')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Expected Delivery Date --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Expected Delivery Date</label>
                                            <input type="date" name="delivery_expected" class="form-control"
                                                value="{{ old('delivery_expected', $booking->delivery_datetime ? \Carbon\Carbon::parse($booking->delivery_datetime)->format('Y-m-d') : ($booking->delivery_date ? \Carbon\Carbon::parse($booking->delivery_date)->format('Y-m-d') : ($booking->delivery_expected ? \Carbon\Carbon::parse($booking->delivery_expected)->format('Y-m-d') : ''))) }}">
                                            @error('delivery_expected')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Travel Cost --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Travel Cost</label>
                                            <div class="input-group">
                                                <span class="input-group-text">&#8377;</span>
                                                <input type="number" name="price" id="travel_cost" class="form-control" step="0.01"
                                                    value="{{ old('price', $booking->price) }}" placeholder="Enter travel cost">
                                            </div>
                                            @error('price')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Estimated Price --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Estimated Price</label>
                                            <div class="input-group">
                                                <span class="input-group-text">&#8377;</span>
                                                <input type="number" name="estimated_price" id="estimated_price" class="form-control" step="0.01"
                                                    value="{{ old('estimated_price', $booking->estimated_price) }}" placeholder="Auto-calculated" readonly>
                                            </div>
                                            <input type="hidden" id="unit_price" value="{{ $sourcePrice ?? 0 }}">
                                            <small class="text-muted">Price per unit: &#8377;<span id="price_display">{{ $sourcePrice ?? 0 }}</span></small>
                                            @error('estimated_price')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
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
                                        {{-- Booking Description --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Booking Status</label>
                                            <textarea name="vendor_booking_description" class="form-control" rows="3"
                                                placeholder="Enter booking status">{{ old('vendor_booking_description', $booking->vendor_booking_description) }}</textarea>
                                            @error('vendor_booking_description')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Vehicle Description --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Vehicle Status</label>
                                            <textarea name="vendor_vehicle_description" class="form-control" rows="3"
                                                placeholder="Enter vehicle status">{{ old('vendor_vehicle_description', $booking->vendor_vehicle_description) }}</textarea>
                                            @error('vendor_vehicle_description')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
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
                                        {{-- Select Driver Dropdown --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Select Driver</label>
                                            <div class="input-group">
                                                <select name="driver_id" id="driver_select" class="form-control select2-driver">
                                                    <option value="">Select Driver (Optional)</option>
                                                    @foreach ($drivers as $driver)
                                                        <option value="{{ $driver->id }}"
                                                            data-name="{{ $driver->name }}"
                                                            data-mobile="{{ $driver->mobile }}"
                                                            {{ old('driver_id', $booking->driver_id) == $driver->id ? 'selected' : '' }}>
                                                            {{ $driver->name }} - {{ $driver->mobile }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="button" class="btn btn-outline-primary" id="add_driver_btn" title="Add New Driver">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                            <input type="hidden" name="driver_name" id="driver_name_hidden" value="{{ old('driver_name', $booking->driver_name) }}">
                                            <input type="hidden" name="driver_mobile" id="driver_mobile_hidden" value="{{ old('driver_mobile', $booking->driver_mobile) }}">
                                        </div>

                                        {{-- Priority --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Priority</label>
                                            <select name="priority" class="form-control">
                                                <option value="">Select Priority</option>
                                                @for ($i = 1; $i <= 20; $i++)
                                                    <option value="{{ $i }}" {{ old('priority', $booking->priority) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                                @endfor
                                            </select>
                                            @error('priority')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Vehicle Number --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Vehicle Number</label>
                                            <input type="text" name="vehicle_number" class="form-control"
                                                value="{{ old('vehicle_number', $booking->vehicle_number) }}" placeholder="Enter vehicle number">
                                            @error('vehicle_number')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Vehicle Start Date --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Vehicle Start Date</label>
                                            <input type="date" name="vehicle_started_date" class="form-control"
                                                value="{{ old('vehicle_started_date', $booking->vehicle_started_date ? \Carbon\Carbon::parse($booking->vehicle_started_date)->format('Y-m-d') : '') }}">
                                            @error('vehicle_started_date')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Vehicle End Date --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Vehicle End Date</label>
                                            <input type="date" name="vehicle_end_date" class="form-control"
                                                value="{{ old('vehicle_end_date', $booking->vehicle_end_date ? \Carbon\Carbon::parse($booking->vehicle_end_date)->format('Y-m-d') : '') }}">
                                            @error('vehicle_end_date')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Vehicle Start Location --}}
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Vehicle Start Location</label>
                                            <div class="input-group">
                                                <input type="text" name="vehicle_start_address" id="vehicle_start_address" class="form-control"
                                                    value="{{ old('vehicle_start_address', $booking->vehicle_start_address) }}" placeholder="Enter or select start location">
                                                <button type="button" class="btn btn-outline-secondary" id="vehicle_start_map_btn" title="Pick from map">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </button>
                                            </div>
                                            <input type="hidden" name="vehicle_start_lat" id="vehicle_start_lat" value="{{ old('vehicle_start_lat', $booking->vehicle_start_lat) }}">
                                            <input type="hidden" name="vehicle_start_lng" id="vehicle_start_lng" value="{{ old('vehicle_start_lng', $booking->vehicle_start_lng) }}">
                                            @error('vehicle_start_address')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Action Buttons --}}
                            <div class="d-flex justify-content-end mt-4 gap-2">
                                <a href="{{ route('bookings.index') }}" class="btn btn-light me-2">
                                    <i class="fas fa-times mr-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i> Update Booking
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Driver Modal --}}
    <div class="modal fade" id="addDriverModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Driver</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Driver Name <span class="text-danger">*</span></label>
                        <input type="text" id="new_driver_name" class="form-control" placeholder="Enter driver name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" id="new_driver_mobile" class="form-control" placeholder="Enter mobile number">
                    </div>
                    <div id="driver_error" class="alert alert-danger d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save_driver_btn">
                        <i class="fas fa-save mr-1"></i> Save Driver
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Map Modal --}}
    <div class="modal fade" id="mapModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Location</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" id="map_search" class="form-control" placeholder="Search for a location...">
                    </div>
                    <div id="map" style="height: 400px; width: 100%;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm_location_btn">Confirm Location</button>
                </div>
            </div>
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
        .input-group .select2-container {
            flex: 1;
        }
        .input-group .select2-container .select2-selection--single {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .gap-2 {
            gap: 0.5rem;
        }
        .pac-container {
            z-index: 100000 !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key', '') }}&libraries=places"></script>

    <script>
        // Data from server
        const hatcheries = @json($hatcheries);
        const spotHatcheries = @json($spotHatcheries);
        const vehicleAvailability = @json($vehicleAvailability);
        const allLocations = @json($locations);
        const currentBookingType = '{{ $booking->is_spot ?? 0 }}';
        const currentHatcheryId = '{{ $booking->hatchery_id }}';

        let map, marker, selectedLocation = null;
        let currentMapTarget = 'dropping'; // 'dropping' or 'vehicle_start'

        function populateSourceDropdown(type, selectedId) {
            let options = '<option value="">Select Source</option>';
            let data = [];

            if (type === '0') {
                data = hatcheries;
            } else if (type === '1') {
                data = spotHatcheries;
            } else if (type === '2') {
                data = vehicleAvailability;
            }

            data.forEach(item => {
                const name = item.hatchery_name || item.vehicle_name || 'Item #' + item.id;
                const isSelected = String(item.id) === String(selectedId) ? 'selected' : '';
                options += `<option value="${item.id}"
                    data-name="${name}"
                    data-category="${item.category_id || ''}"
                    data-brand="${item.brand_id || ''}"
                    data-price="${item.price || 0}"
                    data-vendor="${item.vendor_id || ''}"
                    data-location="${item.location_id || ''}"
                    ${isSelected}>${name}</option>`;
            });

            $('#source_select').html(options);
            if ($('#source_select').hasClass('select2-hidden-accessible')) {
                $('#source_select').trigger('change.select2');
            }
        }

        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2({
                placeholder: "Select an option",
                allowClear: true,
                width: '100%'
            });

            // Initialize Select2 for driver dropdown
            $('.select2-driver').select2({
                placeholder: "Search driver by name or mobile",
                allowClear: true,
                width: 'calc(100% - 46px)'
            });

            // Customer dropdown — auto-fill name & mobile when a customer is picked.
            // The fields stay editable so a new customer can still be typed manually.
            $('.select2-customer').select2({
                placeholder: "Search customer by name or mobile",
                allowClear: true,
                width: '100%'
            });
            $('#customer_select').on('change', function() {
                const selected = $(this).find('option:selected');
                if (!selected.val()) return;
                $('#customer_name_input').val(selected.data('name') || '');
                $('#customer_mobile_input').val(String(selected.data('mobile') || ''));
            });

            // Populate source dropdown on page load with current booking data
            populateSourceDropdown(currentBookingType, currentHatcheryId);

            // Booking Type Change - Repopulate source dropdown
            $('#booking_type').on('change', function() {
                const type = $(this).val();
                populateSourceDropdown(type, '');

                // Reset price
                $('#unit_price').val(0);
                $('#price_display').text('0');
                calculatePrice();
            });

            // Source Change - Update hidden fields, category, and price
            $('#source_select').on('change', function() {
                const selected = $(this).find('option:selected');
                const categoryId = selected.data('category');
                const brandId = selected.data('brand');
                const price = selected.data('price') || 0;
                const vendorId = selected.data('vendor');
                const hatcheryName = selected.data('name');

                // Update hidden fields
                $('#hatchery_name_hidden').val(hatcheryName || '');
                $('#vendor_id_hidden').val(vendorId || '');

                // Update unit price for estimation
                $('#unit_price').val(price);
                $('#price_display').text(price);
                calculatePrice();

                // Auto-select category
                if (categoryId) {
                    $('select[name="category_id"]').val(categoryId);
                } else if (brandId) {
                    $('select[name="category_id"]').val(brandId);
                }

                // Auto-fill unit with location name
                const locationId = selected.data('location');
                if (locationId) {
                    const loc = allLocations.find(l => l.id == locationId);
                    if (loc) {
                        $('input[name="unit"]').val(loc.location_name);
                    }
                }
            });

            // Auto-calculate estimated price = no_of_pieces * source price
            function calculatePrice() {
                const pieces = parseInt($('#no_of_pieces').val()) || 0;
                const unitPrice = parseFloat($('#unit_price').val()) || 0;
                const estimatedPrice = pieces * unitPrice;
                $('#estimated_price').val(estimatedPrice > 0 ? estimatedPrice.toFixed(2) : '');
            }

            $('#no_of_pieces').on('input', function() {
                calculatePrice();
            });

            // Driver Selection - Update hidden fields
            $('#driver_select').on('change', function() {
                const selected = $(this).find('option:selected');
                const driverName = selected.data('name') || '';
                const driverMobile = selected.data('mobile') || '';

                $('#driver_name_hidden').val(driverName);
                $('#driver_mobile_hidden').val(driverMobile);
            });

            // Add Driver Modal
            $('#add_driver_btn').on('click', function() {
                $('#new_driver_name').val('');
                $('#new_driver_mobile').val('');
                $('#driver_error').addClass('d-none');
                $('#addDriverModal').modal('show');
            });

            // Save New Driver
            $('#save_driver_btn').on('click', function() {
                const name = $('#new_driver_name').val().trim();
                const mobile = $('#new_driver_mobile').val().trim();

                if (!name || !mobile) {
                    $('#driver_error').removeClass('d-none').text('Please fill all fields');
                    return;
                }

                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

                $.ajax({
                    url: '{{ route("admin.bookings.storeDriver") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        name: name,
                        mobile: mobile
                    },
                    success: function(response) {
                        if (response.success) {
                            const driver = response.driver;
                            const newOption = new Option(
                                `${driver.name} - ${driver.mobile}`,
                                driver.id,
                                true,
                                true
                            );
                            $(newOption).attr('data-name', driver.name);
                            $(newOption).attr('data-mobile', driver.mobile);
                            $('#driver_select').append(newOption).trigger('change');

                            $('#driver_name_hidden').val(driver.name);
                            $('#driver_mobile_hidden').val(driver.mobile);

                            $('#addDriverModal').modal('hide');
                        }
                    },
                    error: function(xhr) {
                        const errors = xhr.responseJSON?.errors;
                        let errorMsg = 'Failed to save driver';
                        if (errors) {
                            errorMsg = Object.values(errors).flat().join(', ');
                        }
                        $('#driver_error').removeClass('d-none').text(errorMsg);
                    },
                    complete: function() {
                        $('#save_driver_btn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Driver');
                    }
                });
            });

            // Map Picker - Dropping Location
            $('#map_picker_btn').on('click', function() {
                currentMapTarget = 'dropping';
                $('#mapModal .modal-title').text('Select Dropping Location');
                $('#map_search').val('');
                $('#mapModal').modal('show');
                setTimeout(initMap, 300);
            });

            // Map Picker - Vehicle Start Location
            $('#vehicle_start_map_btn').on('click', function() {
                currentMapTarget = 'vehicle_start';
                $('#mapModal .modal-title').text('Select Vehicle Start Location');
                $('#map_search').val('');
                $('#mapModal').modal('show');
                setTimeout(initMap, 300);
            });

            $('#confirm_location_btn').on('click', function() {
                if (selectedLocation) {
                    if (currentMapTarget === 'dropping') {
                        $('#dropping_location').val(selectedLocation.address);
                        $('#drop_lat').val(selectedLocation.lat);
                        $('#drop_lng').val(selectedLocation.lng);
                    } else if (currentMapTarget === 'vehicle_start') {
                        $('#vehicle_start_address').val(selectedLocation.address);
                        $('#vehicle_start_lat').val(selectedLocation.lat);
                        $('#vehicle_start_lng').val(selectedLocation.lng);
                    }
                }
                $('#mapModal').modal('hide');
            });
        });

        function initMap() {
            let existingLat, existingLng;

            if (currentMapTarget === 'vehicle_start') {
                existingLat = parseFloat($('#vehicle_start_lat').val()) || null;
                existingLng = parseFloat($('#vehicle_start_lng').val()) || null;
            } else {
                existingLat = parseFloat($('#drop_lat').val()) || null;
                existingLng = parseFloat($('#drop_lng').val()) || null;
            }

            // If no lat/lng, try geocoding from the address field
            if (!existingLat || !existingLng) {
                var addressField = currentMapTarget === 'vehicle_start' ? '#vehicle_start_address' : '#dropping_location';
                var addressVal = $(addressField).val();
                if (addressVal && addressVal.trim() !== '') {
                    var geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ address: addressVal }, function(results, status) {
                        if (status === 'OK' && results[0]) {
                            var loc = results[0].geometry.location;
                            if (!map) {
                                map = new google.maps.Map(document.getElementById('map'), { center: loc, zoom: 14 });
                                marker = new google.maps.Marker({ position: loc, map: map, draggable: true });
                                setupMapListeners();
                            } else {
                                map.setCenter(loc);
                                map.setZoom(14);
                                marker.setPosition(loc);
                            }
                        }
                    });
                    return;
                }
                existingLat = 20.5937;
                existingLng = 78.9629;
            }

            var hasLocation = existingLat !== 20.5937;
            const defaultLocation = { lat: existingLat, lng: existingLng };

            if (!map) {
                map = new google.maps.Map(document.getElementById('map'), {
                    center: defaultLocation,
                    zoom: hasLocation ? 14 : 5
                });

                marker = new google.maps.Marker({
                    position: defaultLocation,
                    map: map,
                    draggable: true
                });

                setupMapListeners();
            } else {
                map.setCenter(defaultLocation);
                map.setZoom(hasLocation ? 14 : 5);
                marker.setPosition(defaultLocation);
            }

            selectedLocation = null;
        }

        var _mapListenersSet = false;
        function setupMapListeners() {
            if (_mapListenersSet) return;
            _mapListenersSet = true;

            const autocomplete = new google.maps.places.Autocomplete(document.getElementById('map_search'), {
                types: ['geocode', 'establishment'],
                componentRestrictions: { country: 'in' }
            });

            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (!place.geometry) return;

                map.setCenter(place.geometry.location);
                map.setZoom(15);
                marker.setPosition(place.geometry.location);

                selectedLocation = {
                    lat: place.geometry.location.lat(),
                    lng: place.geometry.location.lng(),
                    address: place.formatted_address || place.name
                };
            });

            map.addListener('click', function(e) {
                marker.setPosition(e.latLng);
                getAddressFromLatLng(e.latLng);
            });

            marker.addListener('dragend', function() {
                getAddressFromLatLng(marker.getPosition());
            });
        }

        // Google Places Autocomplete on the dropping location input
        function initAutocomplete() {
            var droppingInput = document.getElementById('dropping_location');
            var autocomplete = new google.maps.places.Autocomplete(droppingInput, {
                types: ['geocode', 'establishment'],
                componentRestrictions: { country: 'in' }
            });

            autocomplete.addListener('place_changed', function() {
                var place = autocomplete.getPlace();
                if (place.geometry) {
                    $('#drop_lat').val(place.geometry.location.lat());
                    $('#drop_lng').val(place.geometry.location.lng());
                    $('#dropping_location').val(place.formatted_address || place.name);
                }
            });
        }
        initAutocomplete();

        function getAddressFromLatLng(latLng) {
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({ location: latLng }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    selectedLocation = {
                        lat: latLng.lat(),
                        lng: latLng.lng(),
                        address: results[0].formatted_address
                    };
                    $('#map_search').val(results[0].formatted_address);
                }
            });
        }
    </script>
@endpush
