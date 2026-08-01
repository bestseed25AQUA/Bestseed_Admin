@extends('admin.layouts.main')

@section('page_title', 'Edit Hatchery')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Edit Hatchery</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Hatchery</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Update Hatchery</h4>
                        @include('flash_msg')

                        <form method="POST" action="{{ route('hatcheries.update', $hatchery->id) }}"
                            enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            {{-- Row 1: Hatchery Name + Vendor --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Hatchery Name</label>
                                    <input type="text" name="hatchery_name" class="form-control"
                                        value="{{ old('hatchery_name', $hatchery->hatchery_name) }}">
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
                                                {{ old('vendor_id', $hatchery->vendor_id) == $vendor->id ? 'selected' : '' }}>
                                                {{ $vendor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('vendor_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Row 2: Phone Numbers --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Call Number</label>
                                    <input type="text" name="call_number" class="form-control"
                                        value="{{ old('call_number', $hatchery->call_number) }}" placeholder="e.g. 9876543210" maxlength="10"
                                        pattern="[0-9]{10}" title="Enter 10 digit phone number">
                                    <small class="text-muted">10-digit phone number for Call Now button in app</small>
                                    @error('call_number')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">WhatsApp Number</label>
                                    <input type="text" name="whatsapp_number" class="form-control"
                                        value="{{ old('whatsapp_number', $hatchery->whatsapp_number) }}" placeholder="e.g. 919876543210" maxlength="12"
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
                                        value="{{ old('facebook_link', $hatchery->facebook_link) }}" placeholder="e.g. https://facebook.com/yourhatchery">
                                    <small class="text-muted">Facebook page URL (shown in updates screen)</small>
                                    @error('facebook_link')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Row 3: Logo + Hatchery Status --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Hatchery Logo</label>
                                    <input type="file" name="logo" class="form-control file-input" accept="image/*">
                                    <small class="text-muted">Upload hatchery logo (Max 5MB, JPG/PNG/GIF)</small>
                                    @if ($hatchery->logo)
                                        <div class="mt-2">
                                            <strong>Current Logo:</strong><br>
                                            <img src="{{ asset($hatchery->logo) }}" alt="Hatchery Logo" width="100"
                                                class="rounded mt-1 border">
                                            <div class="form-check mt-1">
                                                <input type="checkbox" name="remove_logo" id="remove_logo" class="form-check-input" value="1">
                                                <label for="remove_logo" class="form-check-label text-danger small">Remove current logo</label>
                                            </div>
                                        </div>
                                    @endif
                                    @error('logo')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Hatchery Status</label>
                                    <select name="status" class="form-control">
                                        <option value="">Select Status</option>
                                        <option value="1" {{ old('status', $hatchery->status) == '1' ? 'selected' : '' }}>Available</option>
                                        <option value="2" {{ old('status', $hatchery->status) == '2' ? 'selected' : '' }}>Coming Soon</option>
                                        <option value="3" {{ old('status', $hatchery->status) == '3' ? 'selected' : '' }}>Upcoming</option>
                                        <option value="4" {{ old('status', $hatchery->status) == '4' ? 'selected' : '' }}>Shortly Available</option>
                                        <option value="5" {{ old('status', $hatchery->status) == '5' ? 'selected' : '' }}>Closed</option>
                                    </select>
                                    <small class="text-muted">Changing status will update all categories of this hatchery</small>
                                    @error('status')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Active / Inactive — Inactive hides the hatchery category & its data from users --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Visibility (Active / Inactive)</label>
                                    <select name="is_active" class="form-control">
                                        <option value="1" {{ old('is_active', $hatchery->is_active) ? 'selected' : '' }}>Active (visible to users)</option>
                                        <option value="0" {{ !old('is_active', $hatchery->is_active) ? 'selected' : '' }}>Inactive (hidden from users)</option>
                                    </select>
                                    <small class="text-muted">When Inactive, this hatchery category and its posts are hidden from users.</small>
                                </div>
                            </div>

                            {{-- Row 3: Shrimp Category (Multi) + Location + Fish Category (Multi) --}}
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Shrimp Category</label>
                                    <select name="category_id[]" class="form-control select2" multiple
                                        data-placeholder="Select shrimp categories">
                                        @foreach ($shrimpCategories as $cat)
                                            <option value="{{ $cat->id }}"
                                                {{ $hatchery->category_id == $cat->id ? 'selected' : '' }}>
                                                {{ $cat->category_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Location</label>
                                    <select name="location_id" class="form-control select2"
                                        data-placeholder="Select location">
                                        <option value="">Select Location</option>
                                        @foreach ($locations as $loc)
                                            <option value="{{ $loc->id }}"
                                                {{ old('location_id', $hatchery->location_id) == $loc->id ? 'selected' : '' }}>
                                                {{ $loc->location_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('location_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                {{-- Hatchery pickup lat/lng — feeds RouteTimelineService so the
                                     customer's vehicle-tracking timeline knows where the route
                                     starts. Without these, intermediate stops can't be generated
                                     for bookings from this hatchery. --}}
                                <div class="col-md-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="number" step="any" name="lat"
                                        value="{{ old('lat', $hatchery->lat) }}"
                                        class="form-control" placeholder="e.g. 17.441607">
                                    @error('lat')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="number" step="any" name="lng"
                                        value="{{ old('lng', $hatchery->lng) }}"
                                        class="form-control" placeholder="e.g. 78.377168">
                                    @error('lng')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-md-6 d-flex align-items-end">
                                    <a href="https://www.google.com/maps" target="_blank"
                                       class="btn btn-sm btn-outline-secondary">
                                       Open Google Maps to pick coordinates
                                    </a>
                                    <small class="text-muted ms-2">
                                       Right-click the spot on Maps → copy the two numbers it shows.
                                    </small>
                                </div>

                                {{-- Interactive map picker — fills the Latitude/Longitude above and,
                                     on save, is stored onto the selected Location so the "similar
                                     hatcheries within 150km" filter works. --}}
                                <div class="col-md-12 mt-2 mb-2">
                                    <label class="form-label">Pick location on map</label>
                                    <input type="text" id="map_search" class="form-control mb-2"
                                        placeholder="Search a place…">
                                    <div id="hatchery_map"
                                        style="width:100%;height:300px;border-radius:8px;border:1px solid #ddd;"></div>
                                    <small class="text-muted">Search, click the map, or drag the marker.
                                        Selecting a Location above also fills these. Coordinates are saved
                                        to the selected Location.</small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Fish Category</label>
                                    <select name="fish_category_id[]" class="form-control select2" multiple
                                        data-placeholder="Select fish categories">
                                        @foreach ($fishCategories as $fish)
                                            <option value="{{ $fish->id }}"
                                                {{ $hatchery->brand_id == $fish->id ? 'selected' : '' }}>
                                                {{ $fish->category_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('brand_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Row 4: Branch + Hatchery Status --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Branch</label>
                                    @php
                                        $savedBranchIds = [];
                                        if (isset($hatcheryBranches) && count($hatcheryBranches) > 0) {
                                            $savedBranchIds = $hatcheryBranches
                                                ->pluck('branch_name')
                                                ->map(fn($v) => (int) $v)
                                                ->toArray();
                                        }
                                        $branchIds = old('branch_id', $savedBranchIds);
                                        if (!is_array($branchIds)) {
                                            $branchIds = [$branchIds];
                                        }
                                        $branchIds = array_map('intval', array_filter($branchIds));
                                    @endphp
                                    <select name="branch_id[]" class="form-control select2" multiple="multiple"
                                        data-placeholder="Select branches">
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}"
                                                {{ in_array((int) $branch->id, $branchIds) ? 'selected' : '' }}>
                                                {{ $branch->branch_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('branch_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Dynamic Category Details --}}
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label"><strong>Category Details</strong></label>
                                    <input type="hidden" name="removed_images" id="removed_images" value="">
                                    <div id="category-price-box" class="p-3 border rounded" style="background:#f9f9f9;">
                                        <p class="text-muted">No category selected.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Update</button>
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
            min-height: 45px;
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
            height: 45px !important;
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

        .gap-2 { gap: 0.5rem; }

        .category-detail-card {
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .category-detail-card .card-header { font-size: 14px; }
        .category-detail-card .card-body { background: #fff; }

        #category-price-box {
            max-height: 600px;
            overflow-y: auto;
        }

        .media-item {
            position: relative;
            display: inline-block;
            margin: 5px;
        }

        .remove-media-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 22px;
            height: 22px;
            padding: 0;
            font-size: 14px;
            line-height: 20px;
            text-align: center;
            border-radius: 50%;
            background-color: #dc3545;
            color: white;
            border: none;
            cursor: pointer;
            z-index: 10;
        }

        .remove-media-btn:hover {
            background-color: #c82333;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Data from server
        const shrimpCategoriesData = @json($shrimpCategories);
        const fishCategoriesData = @json($fishCategories);
        const assetBase = '{{ asset("") }}';

        // Existing hatchery data for pre-fill
        const existingData = {
            type: '{{ $hatchery->category_id ? "shrimp" : "fish" }}',
            catId: {{ $hatchery->category_id ?? $hatchery->brand_id ?? 'null' }},
            price: @json($hatchery->price ?? ''),
            broodstock_count: @json($hatchery->broodstock_count ?? ''),
            description: @json($hatchery->description ?? ''),
            available_on: '{{ $hatchery->available_on ? \Carbon\Carbon::parse($hatchery->available_on)->format("Y-m-d") : "" }}',
            hatchery_status: @json($hatchery->hatchery_status ?? ''),
            report: @json($hatchery->report ?? ''),
        };
        const existingImages = @json($rawImages ?? []);

        // Track removed images
        let removedImages = [];

        $(document).ready(function() {
            $('.select2').select2({
                placeholder: function() {
                    return $(this).data('placeholder');
                },
                width: '100%',
                allowClear: true
            });

            // Render category cards on page load
            renderCategoryCards();
        });

        // Category change handlers
        $('select[name="category_id[]"], select[name="fish_category_id[]"]').on('change', function() {
            renderCategoryCards();
        });

        function renderCategoryCards() {
            let shrimpSelected = $('select[name="category_id[]"]').val() || [];
            let fishSelected = $('select[name="fish_category_id[]"]').val() || [];

            if (shrimpSelected.length === 0 && fishSelected.length === 0) {
                $("#category-price-box").html("<p class='text-muted'>No category selected.</p>");
                return;
            }

            $("#category-price-box").html("");

            // Render shrimp category cards
            shrimpSelected.forEach(function(catId) {
                let cat = shrimpCategoriesData.find(c => c.id == catId);
                if (!cat) return;
                let isExisting = existingData.type === 'shrimp' && existingData.catId == catId;
                appendCategoryCard('shrimp', catId, cat.category_name, isExisting);
            });

            // Render fish category cards
            fishSelected.forEach(function(catId) {
                let cat = fishCategoriesData.find(c => c.id == catId);
                if (!cat) return;
                let isExisting = existingData.type === 'fish' && existingData.catId == catId;
                appendCategoryCard('fish', catId, cat.category_name, isExisting);
            });

        }

        function appendCategoryCard(type, catId, name, isExisting) {
            let price = isExisting ? (existingData.price || '') : '';
            let broodstock = isExisting ? (existingData.broodstock_count || '') : '';
            let availableOn = isExisting ? (existingData.available_on || '') : '';
            let status = isExisting ? (existingData.hatchery_status || '') : '';

            let headerEmoji = type === 'shrimp' ? '&#129424;' : '&#128031;';
            let bgClass = type === 'shrimp' ? 'bg-info' : 'bg-primary';
            let badge = isExisting
                ? '<span class="badge badge-light ml-2" style="color:#333;">Existing</span>'
                : '<span class="badge badge-light ml-2" style="color:#333;">New</span>';

            // Build existing media HTML
            let existingMediaHtml = '';
            if (isExisting && existingImages.length > 0) {
                let mediaItems = '';
                existingImages.forEach(function(path) {
                    let ext = path.split('.').pop().toLowerCase();
                    let assetUrl = assetBase + path;
                    let isVideo = ['mp4', 'avi', 'mov', 'webm'].includes(ext);

                    if (isVideo) {
                        mediaItems += '<div class="position-relative media-item" data-path="' + path + '">' +
                            '<video width="80" height="60" controls class="rounded">' +
                            '<source src="' + assetUrl + '">' +
                            '</video>' +
                            '<button type="button" class="btn btn-danger btn-sm remove-media-btn" ' +
                            'onclick="removeMedia(this, \'' + path.replace(/'/g, "\\'") + '\')">&times;</button>' +
                            '</div>';
                    } else {
                        mediaItems += '<div class="position-relative media-item" data-path="' + path + '">' +
                            '<img src="' + assetUrl + '" width="80" height="60" class="rounded" style="object-fit:cover;">' +
                            '<button type="button" class="btn btn-danger btn-sm remove-media-btn" ' +
                            'onclick="removeMedia(this, \'' + path.replace(/'/g, "\\'") + '\')">&times;</button>' +
                            '</div>';
                    }
                });

                existingMediaHtml = '<div class="mt-2">' +
                    '<strong>Existing Media:</strong>' +
                    '<div class="d-flex flex-wrap gap-2 mt-1" id="existing-media-container">' +
                    mediaItems +
                    '</div></div>';
            }

            // Build existing report HTML
            let existingReportHtml = '';
            if (isExisting && existingData.report) {
                let reportUrl = assetBase + existingData.report;
                existingReportHtml = '<div class="mt-2">' +
                    '<strong>Current Report:</strong> ' +
                    '<a href="' + reportUrl + '" target="_blank" class="btn btn-sm btn-outline-primary">' +
                    '<i class="fas fa-file-download"></i> View Report</a></div>';
            }

            // Build status options
            let statusOptions = '<option value="">Select Status</option>';
            [{v:'1',l:'Available'},{v:'2',l:'Coming Soon'},{v:'3',l:'Upcoming'},{v:'4',l:'Shortly Available'},{v:'5',l:'Closed'}]
            .forEach(function(opt) {
                let selected = (String(status) === opt.v) ? 'selected' : '';
                statusOptions += '<option value="' + opt.v + '" ' + selected + '>' + opt.l + '</option>';
            });

            let html = '<div class="card mb-3 category-detail-card">' +
                '<div class="card-header ' + bgClass + ' text-white py-2">' +
                '<strong>' + headerEmoji + ' ' + name + '</strong> ' + badge +
                '</div>' +
                '<div class="card-body">' +
                '<div class="row">' +
                '<div class="col-md-3 mb-2">' +
                '<label class="form-label">Price</label>' +
                '<input type="number" step="0.01" name="category_details[' + type + '][' + catId + '][price]" class="form-control" placeholder="Enter price" value="' + price + '">' +
                '</div>' +
                '<div class="col-md-3 mb-2">' +
                '<label class="form-label">Broodstock Count</label>' +
                '<input type="number" name="category_details[' + type + '][' + catId + '][broodstock_count]" class="form-control" placeholder="Enter count" value="' + broodstock + '">' +
                '</div>' +
                '<div class="col-md-6 mb-2">' +
                '<label class="form-label">Description</label>' +
                '<input type="text" name="category_details[' + type + '][' + catId + '][description]" class="form-control" placeholder="Enter description">' +
                '</div>' +
                '</div>' +
                '<div class="row mt-2">' +
                '<div class="col-md-6 mb-2">' +
                '<label class="form-label">Images & Videos</label>' +
                '<input type="file" name="category_media[' + type + '][' + catId + '][]" class="form-control file-input" multiple accept="image/*,video/*">' +
                '<small class="text-muted">Multiple images/videos</small>' +
                existingMediaHtml +
                '</div>' +
                '<div class="col-md-6 mb-2">' +
                '<label class="form-label">Report (PDF / Doc)</label>' +
                '<input type="file" name="category_report[' + type + '][' + catId + ']" class="form-control file-input" accept=".pdf,.doc,.docx">' +
                '<small class="text-muted">Upload report document</small>' +
                existingReportHtml +
                '</div>' +
                '</div>' +
                '<div class="row mt-2">' +
                '<div class="col-md-6 mb-2">' +
                '<label class="form-label">Available On</label>' +
                '<input type="date" name="category_details[' + type + '][' + catId + '][available_on]" class="form-control" value="' + availableOn + '">' +
                '</div>' +
                '<div class="col-md-6 mb-2">' +
                '<label class="form-label">Status</label>' +
                '<select name="category_details[' + type + '][' + catId + '][status]" class="form-control">' +
                statusOptions +
                '</select>' +
                '</div>' +
                '</div>' +
                '</div></div>';

            $("#category-price-box").append(html);

            // Set description value via jQuery to handle special characters safely
            if (isExisting && existingData.description) {
                $('input[name="category_details[' + type + '][' + catId + '][description]"]').val(existingData.description);
            }
        }

        function removeMedia(btn, path) {
            removedImages.push(path);
            $('#removed_images').val(JSON.stringify(removedImages));
            $(btn).closest('.media-item').fadeOut(300, function() {
                $(this).remove();
                if ($('#existing-media-container .media-item').length === 0) {
                    $('#existing-media-container').parent().html(
                        '<div class="mt-2 text-muted"><small>No existing media.</small></div>');
                }
            });
        }

        // Location change - fetch branches
        $('select[name="location_id"]').on('change', function() {
            let locationId = $(this).val();
            if (locationId) {
                $.get('/admin/get-branches/' + locationId, function(data) {
                    let branchSelect = $('select[name="branch_id[]"]');
                    branchSelect.empty();
                    data.forEach(function(b) {
                        branchSelect.append('<option value="' + b.id + '">' + b.branch_name + '</option>');
                    });
                    branchSelect.trigger('change');
                });
            }
        });
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

            // Show WHAT WAS LAST SELECTED: reverse-geocode the saved pin and put
            // its address in the search box so the admin sees the previously
            // chosen location (the marker already shows the spot on the map).
            if (has && search) {
                new google.maps.Geocoder().geocode(
                    { location: { lat: sLat, lng: sLng } },
                    function (results, status) {
                        if (status === 'OK' && results && results[0]) {
                            search.value = results[0].formatted_address;
                            search.setAttribute('title', results[0].formatted_address);
                        }
                    }
                );
            }

            // Selecting a Location prefills its saved coords (only when the lat/lng
            // are still empty, so we don't overwrite the hatchery's existing point).
            if (window.jQuery) {
                jQuery('select[name="location_id"]').on('change', function () {
                    var c = locationCoords[this.value];
                    var empty = (!latInput.value && !lngInput.value);
                    if (empty && c && c.lat !== null && c.lat !== '' && c.lng !== null && c.lng !== '') {
                        place(c.lat, c.lng, true);
                    }
                });
            }
        })();
    </script>
@endpush
