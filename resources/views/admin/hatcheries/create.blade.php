@extends('admin.layouts.main')

@section('page_title', 'Register Hatchery')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Register Hatchery</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create Hatchery</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Create Hatchery</h4>
                        @include('flash_msg')

                        <form method="POST" action="{{ route('hatcheries.store') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Hatchery Name</label>
                                    <input type="text" name="hatchery_name" class="form-control"
                                        value="{{ old('hatchery_name') }}">
                                    @error('hatchery_name')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Select Vendor</label>
                                    <select name="vendor_id" class="form-control">
                                        <option value="">Choose Vendor</option>
                                        @foreach ($vendors as $vendor)
                                            <option value="{{ $vendor->id }}"
                                                {{ old('vendor_id') == $vendor->id ? 'selected' : '' }}>
                                                {{ $vendor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('vendor_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Call Number</label>
                                    <input type="text" name="call_number" class="form-control"
                                        value="{{ old('call_number') }}" placeholder="e.g. 9876543210" maxlength="10"
                                        pattern="[0-9]{10}" title="Enter 10 digit phone number">
                                    <small class="text-muted">10-digit phone number for Call Now button in app</small>
                                    @error('call_number')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">WhatsApp Number</label>
                                    <input type="text" name="whatsapp_number" class="form-control"
                                        value="{{ old('whatsapp_number') }}" placeholder="e.g. 919876543210" maxlength="12"
                                        title="Enter WhatsApp number with country code (e.g. 919876543210)">
                                    <small class="text-muted">WhatsApp number with country code (e.g. 919876543210)</small>
                                    @error('whatsapp_number')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Facebook Link</label>
                                    <input type="url" name="facebook_link" class="form-control"
                                        value="{{ old('facebook_link') }}" placeholder="e.g. https://facebook.com/yourhatchery">
                                    <small class="text-muted">Facebook page URL (shown in updates screen)</small>
                                    @error('facebook_link')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Hatchery Logo</label>
                                    <input type="file" name="logo" class="form-control file-input" accept="image/*">
                                    <small class="text-muted">Upload hatchery logo (Max 5MB, JPG/PNG/GIF)</small>
                                    @error('logo')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Hatchery Status</label>
                                    <select name="status" class="form-control">
                                        <option value="">Select Status</option>
                                        <option value="1" {{ old('status') == '1' ? 'selected' : '' }}>Available</option>
                                        <option value="2" {{ old('status') == '2' ? 'selected' : '' }}>Coming Soon</option>
                                        <option value="3" {{ old('status') == '3' ? 'selected' : '' }}>Upcoming</option>
                                        <option value="4" {{ old('status') == '4' ? 'selected' : '' }}>Shortly Available</option>
                                        <option value="5" {{ old('status') == '5' ? 'selected' : '' }}>Closed</option>
                                    </select>
                                    <small class="text-muted">This status applies to all categories of this hatchery</small>
                                    @error('status')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Multi-select Categories and Locations --}}
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Shrimp Category</label>
                                    <select name="category_id[]" class="form-control select2" multiple
                                        data-placeholder="Select shrimp category">
                                        @foreach ($shrimpCategories as $cat)
                                            <option value="{{ $cat->id }}"
                                                {{ collect(old('category_id'))->contains($cat->id) ? 'selected' : '' }}>
                                                {{ $cat->category_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Locations</label>
                                    {{-- <select name="location_id[]" class="form-control select2" multiple="multiple" data-placeholder="Select locations"> --}}
                                    <select name="location_id" class="form-control select2"
                                        data-placeholder="Select location">
                                        @foreach ($locations as $loc)
                                            <option value="{{ $loc->id }}"
                                                {{ collect(old('location_id'))->contains($loc->id) ? 'selected' : '' }}>
                                                {{ $loc->location_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('location_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                {{-- Map picker: sets Latitude/Longitude and saves them onto the
                                     selected Location, so the "similar hatcheries within 150km"
                                     filter (which reads the location's coords) works. --}}
                                <div class="col-md-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="text" name="lat" id="lat_input" class="form-control"
                                        value="{{ old('lat') }}" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="text" name="lng" id="lng_input" class="form-control"
                                        value="{{ old('lng') }}" readonly>
                                </div>
                                <div class="col-md-12 mt-2 mb-2">
                                    <label class="form-label">Pick location on map</label>
                                    <input type="text" id="map_search" class="form-control mb-2"
                                        placeholder="Search a place…">
                                    <div id="hatchery_map"
                                        style="width:100%;height:300px;border-radius:8px;border:1px solid #ddd;"></div>
                                    <small class="text-muted">Search, click the map, or drag the marker.
                                        Selecting a Location above also fills these. Coordinates are saved to
                                        the selected Location.</small>
                                </div>
                                <!-- branches acc to nabeela -->
                                <div class="col-md-4">
                                    <label class="form-label">Branch</label>
                                    <select name="branch_id[]" class="form-control select2" multiple="multiple"
                                        data-placeholder="Select branches">
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}"
                                                {{ collect(old('branch_id'))->contains($loc->id) ? 'selected' : '' }}>
                                                {{ $branch->branch_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('location_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <!-- branches acc to nabeela -->
                                <!--brand-->
                                <div class="col-md-4">
                                    <label class="form-label">Fish Category</label>
                                    <select name="fish_category_id[]" class="form-control select2" multiple
                                        data-placeholder="Select fish category">
                                        @foreach ($fishCategories as $cat)
                                            <option value="{{ $cat->id }}"
                                                {{ collect(old('fish_category_id'))->contains($cat->id) ? 'selected' : '' }}>
                                                {{ $cat->category_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('brand_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <!-- Category Details Box (Shrimp + Fish) -->
                                <div class="col-md-12 mt-3">
                                    <label class="form-label"><strong>Category Details</strong></label>

                                    <div id="category-price-box" class="p-3 border rounded" style="background:#f9f9f9;">
                                        <p class="text-muted">No category selected.</p>
                                    </div>
                                </div>

                                <!--brand-->
                            </div>

                            <!-- File Size Error Alert -->
                            <div id="fileSizeError" class="alert alert-danger d-none" role="alert">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <strong>File size exceeded!</strong> Maximum allowed size is <strong>50MB</strong> per file.
                                Please select smaller files.
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Register</button>
                                <a href="{{ route('hatcheries.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container--default .select2-selection--multiple {
            min-height: 40px;
            height: auto !important;
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 4px 8px;
            overflow: hidden;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            padding: 0;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            margin: 3px 4px;
            padding: 4px 8px 4px 24px;
            line-height: 18px;
            position: relative;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            position: absolute;
            left: 6px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            color: #fff;
            cursor: pointer;
        }

        .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }

        .select2-container--default .select2-selection--single {
            height: 40px !important;
            border-radius: 6px;
            border: 1px solid #ced4da;
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

        /* Category detail cards */
        .category-detail-card {
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .category-detail-card .card-header {
            font-size: 14px;
        }

        .category-detail-card .card-body {
            background: #fff;
        }

        #category-price-box {
            max-height: 600px;
            overflow-y: auto;
        }
    </style>
@endpush


@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: function() {
                    return $(this).data('placeholder');
                },
                width: '100%'
            });
        });


        // 🍤🐟 Dynamic Category Details for Shrimp + Fish Category
        $('select[name="category_id[]"], select[name="fish_category_id[]"]').on('change', function() {

            let shrimpSelected = $('select[name="category_id[]"]').val() || [];
            let fishSelected = $('select[name="fish_category_id[]"]').val() || [];

            let shrimpCategories = @json($shrimpCategories);
            let fishCategories = @json($fishCategories);

            $("#category-price-box").html(""); // reset box

            // If no selection
            if (shrimpSelected.length === 0 && fishSelected.length === 0) {
                $("#category-price-box").html("<p class='text-muted'>No category selected.</p>");
                return;
            }

            // Add shrimp categories first
            shrimpSelected.forEach(function(catId) {
                let cat = shrimpCategories.find(c => c.id == catId);

                $("#category-price-box").append(`
                    <div class="card mb-3 category-detail-card">
                        <div class="card-header bg-info text-white py-2">
                            <strong>🦐 ${cat.category_name}</strong>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Price</label>
                                    <input type="number" step="0.01" name="category_details[shrimp][${catId}][price]"
                                           class="form-control" placeholder="Enter price">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Broodstock Count</label>
                                    <input type="number" name="category_details[shrimp][${catId}][broodstock_count]"
                                           class="form-control" placeholder="Enter count">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="category_details[shrimp][${catId}][description]"
                                           class="form-control" placeholder="Enter description">
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Images & Videos</label>
                                    <input type="file" name="category_media[shrimp][${catId}][]"
                                           class="form-control file-input" multiple accept="image/*,video/*">
                                    <small class="text-muted">Multiple images/videos (Max 50MB each)</small>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Report (PDF / Doc)</label>
                                    <input type="file" name="category_report[shrimp][${catId}]"
                                           class="form-control file-input" accept=".pdf,.doc,.docx">
                                    <small class="text-muted">Upload report document</small>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Available On</label>
                                    <input type="date" name="category_details[shrimp][${catId}][available_on]"
                                           class="form-control">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Status</label>
                                    <select name="category_details[shrimp][${catId}][status]" class="form-control">
                                        <option value="">Select Status</option>
                                        <option value="1">Available</option>
                                        <option value="2">Coming Soon</option>
                                        <option value="3">Upcoming</option>
                                        <option value="4">Shortly Available</option>
                                        <option value="5">Closed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            });

            // Add fish categories
            // Add fish categories
            fishSelected.forEach(function(catId) {
                let cat = fishCategories.find(c => c.id == catId);
                if (!cat) return;

                $("#category-price-box").append(`
                    <div class="card mb-3 category-detail-card">
                        <div class="card-header bg-primary text-white py-2">
                            <strong>🐟 ${cat.category_name}</strong>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Price</label>
                                    <input type="number" step="0.01"
                                        name="category_details[fish][${catId}][price]"
                                        class="form-control" placeholder="Enter price">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Broodstock Count</label>
                                    <input type="number"
                                        name="category_details[fish][${catId}][broodstock_count]"
                                        class="form-control" placeholder="Enter count">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Description</label>
                                    <input type="text"
                                        name="category_details[fish][${catId}][description]"
                                        class="form-control" placeholder="Enter description">
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Images & Videos</label>
                                    <input type="file"
                                        name="category_media[fish][${catId}][]"
                                        class="form-control file-input" multiple accept="image/*,video/*">
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Report (PDF / Doc)</label>
                                    <input type="file"
                                        name="category_report[fish][${catId}]"
                                        class="form-control file-input" accept=".pdf,.doc,.docx">
                                    <small class="text-muted">Upload report document</small>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Available On</label>
                                    <input type="date" name="category_details[fish][${catId}][available_on]"
                                           class="form-control">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Status</label>
                                    <select name="category_details[fish][${catId}][status]" class="form-control">
                                        <option value="">Select Status</option>
                                        <option value="1">Available</option>
                                        <option value="2">Coming Soon</option>
                                        <option value="3">Upcoming</option>
                                        <option value="4">Shortly Available</option>
                                        <option value="5">Closed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            });

            // Re-attach file size validation to new inputs
            attachFileSizeValidation();

        });



        $('select[name="location_id"]').on('change', function() {
            // let locationId = $(this).val()[0];
            let locationId = $(this).val();

            $.get(`/admin/get-branches/${locationId}`, function(data) {
                let branchSelect = $('select[name="branch_id[]"]');
                branchSelect.empty();

                data.forEach(b => {
                    branchSelect.append(`<option value="${b.id}">${b.branch_name}</option>`);
                });
            });
        });

        // File size validation (50MB max per file)
        const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB in bytes

        function attachFileSizeValidation() {
            $(document).off('change', '.file-input').on('change', '.file-input', function() {
                validateFileSize(this);
            });
        }

        function validateFileSize(input) {
            let files = input.files;
            let hasError = false;
            let errorFiles = [];

            for (let i = 0; i < files.length; i++) {
                if (files[i].size > MAX_FILE_SIZE) {
                    hasError = true;
                    let sizeMB = (files[i].size / (1024 * 1024)).toFixed(2);
                    errorFiles.push(`${files[i].name} (${sizeMB}MB)`);
                }
            }

            if (hasError) {
                // Clear the input
                $(input).val('');

                // Show error message
                $('#fileSizeError').removeClass('d-none');
                $('#fileSizeError').html(`
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>File size exceeded!</strong> Maximum allowed size is <strong>50MB</strong> per file.<br>
                    The following files are too large: <br>
                    <ul class="mb-0 mt-2">
                        ${errorFiles.map(f => `<li>${f}</li>`).join('')}
                    </ul>
                `);

                // Scroll to error
                $('html, body').animate({
                    scrollTop: $('#fileSizeError').offset().top - 100
                }, 500);
            } else {
                $('#fileSizeError').addClass('d-none');
            }
        }

        // Form submit validation
        $('form').on('submit', function(e) {
            let allInputs = $(this).find('input[type="file"]');
            let totalSize = 0;
            let hasLargeFile = false;

            allInputs.each(function() {
                let files = this.files;
                for (let i = 0; i < files.length; i++) {
                    totalSize += files[i].size;
                    if (files[i].size > MAX_FILE_SIZE) {
                        hasLargeFile = true;
                    }
                }
            });

            if (hasLargeFile) {
                e.preventDefault();
                $('#fileSizeError').removeClass('d-none');
                $('html, body').animate({
                    scrollTop: $('#fileSizeError').offset().top - 100
                }, 500);
                return false;
            }

            // Check total size (150MB max)
            if (totalSize > 150 * 1024 * 1024) {
                e.preventDefault();
                $('#fileSizeError').removeClass('d-none').html(`
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Total upload size exceeded!</strong> Maximum total upload size is <strong>150MB</strong>.<br>
                    Current total: ${(totalSize / (1024 * 1024)).toFixed(2)}MB. Please select smaller or fewer files.
                `);
                $('html, body').animate({
                    scrollTop: $('#fileSizeError').offset().top - 100
                }, 500);
                return false;
            }
        });

        // Initial attachment
        attachFileSizeValidation();
    </script>
@endpush

@push('scripts')
    {{-- Interactive map picker for the hatchery's coordinates. --}}
    @php
        $locationCoords = $locations->mapWithKeys(function ($l) {
            return [$l->id => ['lat' => $l->latitude, 'lng' => $l->longitude]];
        });
    @endphp
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=places"></script>
    <script>
        (function () {
            var locationCoords = @json($locationCoords);
            var latInput = document.querySelector('input[name="lat"]');
            var lngInput = document.querySelector('input[name="lng"]');
            var mapEl = document.getElementById('hatchery_map');
            if (!mapEl || !window.google || !google.maps) return;
            var map, marker;

            function setLatLng(lat, lng) {
                if (latInput) latInput.value = (lat || lat === 0) ? Number(lat).toFixed(6) : '';
                if (lngInput) lngInput.value = (lng || lng === 0) ? Number(lng).toFixed(6) : '';
            }
            function place(lat, lng, recenter) {
                lat = Number(lat); lng = Number(lng);
                if (isNaN(lat) || isNaN(lng)) return;
                var pos = { lat: lat, lng: lng };
                if (!marker) {
                    marker = new google.maps.Marker({ position: pos, map: map, draggable: true });
                    marker.addListener('dragend', function (e) {
                        setLatLng(e.latLng.lat(), e.latLng.lng());
                    });
                } else { marker.setPosition(pos); }
                if (recenter) { map.setCenter(pos); map.setZoom(13); }
                setLatLng(lat, lng);
            }

            var sLat = parseFloat(latInput && latInput.value);
            var sLng = parseFloat(lngInput && lngInput.value);
            var has = !isNaN(sLat) && !isNaN(sLng);
            map = new google.maps.Map(mapEl, {
                center: has ? { lat: sLat, lng: sLng } : { lat: 20.5937, lng: 78.9629 },
                zoom: has ? 13 : 4,
            });
            if (has) place(sLat, sLng, false);

            map.addListener('click', function (e) { place(e.latLng.lat(), e.latLng.lng(), false); });

            var search = document.getElementById('map_search');
            if (search && google.maps.places) {
                var ac = new google.maps.places.Autocomplete(search);
                ac.addListener('place_changed', function () {
                    var p = ac.getPlace();
                    if (p.geometry) place(p.geometry.location.lat(), p.geometry.location.lng(), true);
                });
            }

            // Selecting a Location prefills its saved coords (select2 fires a
            // jQuery change, so bind via jQuery).
            if (window.jQuery) {
                jQuery('select[name="location_id"]').on('change', function () {
                    var c = locationCoords[this.value];
                    if (c && c.lat !== null && c.lat !== '' && c.lng !== null && c.lng !== '') {
                        place(c.lat, c.lng, true);
                    }
                });
            }
        })();
    </script>
@endpush
