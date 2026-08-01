@extends('admin.layouts.main')

@section('page_title', 'Register Vehicle Availability')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Register Vehicle Availability</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create Vehicle Availability</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Create Vehicle Availability</h4>
                        @include('flash_msg')

                        <form method="POST" action="{{ route('vehicle-availability.store') }}"
                            enctype="multipart/form-data">
                            @csrf

                            {{-- Row 1: Vehicle Name and Select Hatchery --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Vehicle Availability Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="vehicle_name" id="vehicle_name" class="form-control"
                                        value="{{ old('vehicle_name') }}" required
                                        placeholder="Enter vehicle availability name">
                                    @error('vehicle_name')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Select Hatchery (Auto-fill details)</label>
                                    <select name="hatchery_id" id="hatchery_select" class="form-control select2"
                                        data-placeholder="Search and select a hatchery">
                                        <option value="">Choose Hatchery</option>
                                        @foreach ($hatcheries as $hatchery)
                                            <option value="{{ $hatchery->id }}"
                                                {{ old('hatchery_id') == $hatchery->id ? 'selected' : '' }}>
                                                {{ $hatchery->picker_label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('hatchery_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Row 2: Category and Vendor --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" id="category_id" class="form-control select2"
                                        data-placeholder="Select category">
                                        <option value="">Choose Category</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                                {{ $category->category_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Vendor <span class="text-danger">*</span></label>
                                    <select name="vendor_id" id="vendor_id" class="form-control select2"
                                        data-placeholder="Select vendor" required>
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

                            {{-- Row 3: Location and Branch --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Location <span class="text-danger">*</span></label>
                                    <select name="location_id" id="location_id" class="form-control select2"
                                        data-placeholder="Select location" required>
                                        <option value="">Choose Location</option>
                                        @foreach ($locations as $loc)
                                            <option value="{{ $loc->id }}"
                                                {{ old('location_id') == $loc->id ? 'selected' : '' }}>
                                                {{ $loc->location_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('location_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Branch</label>
                                    <select name="branch_id[]" id="branch_id" class="form-control select2"
                                        multiple="multiple" data-placeholder="Select branches">
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}"
                                                {{ collect(old('branch_id'))->contains($branch->id) ? 'selected' : '' }}>
                                                {{ $branch->branch_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('branch_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Row 5: Description --}}
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="description" class="form-control" rows="3" placeholder="Enter description">{{ old('description') }}</textarea>
                                    @error('description')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Row 6: Route Locations --}}
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Route Locations (Start to End) <span
                                            class="text-danger">*</span></label>
                                    <select name="location_ids[]" class="form-control" id="route_locations" multiple="multiple"
                                        data-placeholder="Select locations in route order" required>
                                        @foreach ($locations as $loc)
                                            <option value="{{ $loc->id }}"
                                                {{ collect(old('location_ids'))->contains($loc->id) ? 'selected' : '' }}>
                                                {{ $loc->location_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">Select locations in order from start point to end
                                        point</small>
                                    @error('location_ids')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Row 7: Start Date, End Date, Available Space --}}
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" id="start_date" class="form-control"
                                        value="{{ old('start_date') }}">
                                    @error('start_date')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" id="end_date" class="form-control"
                                        value="{{ old('end_date') }}">
                                    @error('end_date')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Available Space</label>
                                    <input type="number" name="available_space" id="available_space"
                                        class="form-control" value="{{ old('available_space') }}" min="0"
                                        placeholder="Enter capacity">
                                    @error('available_space')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Row 8: Available On, Price, Status --}}
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Available On</label>
                                    <input type="date" name="available_on" id="available_on" class="form-control"
                                        value="{{ old('available_on') }}">
                                    @error('available_on')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Price</label>
                                    <input type="number" step="0.01" name="price" id="price"
                                        class="form-control" value="{{ old('price') }}" placeholder="Enter price">
                                    @error('price')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="is_active" class="form-control">
                                        <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Vehicle Gallery Section -->
                            <div class="card border mt-4 mb-3">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-images mr-2"></i>Vehicle Gallery</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="form-label">Upload Images & Videos</label>
                                        <input type="file" name="gallery_images[]" class="form-control" multiple
                                            accept="image/*,video/*" id="gallery_input">
                                        <small class="form-text text-muted">
                                            Supported formats: JPG, PNG, GIF, MP4, MOV, AVI, WEBM. Max 50MB per file.
                                        </small>
                                    </div>
                                    <div id="gallery_preview" class="d-flex flex-wrap gap-2 mt-3">
                                    </div>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Register</button>
                                <a href="{{ route('vehicle-availability.index') }}" class="btn btn-light">Cancel</a>
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

        .gallery-preview-item {
            position: relative;
            width: 120px;
            height: 120px;
        }

        .gallery-preview-item img,
        .gallery-preview-item video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .gallery-preview-item .remove-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 12px;
        }

        .gallery-preview-item .file-type-badge {
            position: absolute;
            bottom: 4px;
            left: 4px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for regular dropdowns
            $('.select2').select2({
                placeholder: function() {
                    return $(this).data('placeholder');
                },
                width: '100%',
                allowClear: true
            });

            // Route Locations: preserve selection order (click order, not priority)
            var $routeLocations = $('#route_locations');
            $routeLocations.select2({
                placeholder: 'Select locations in route order',
                width: '100%',
                allowClear: true
            });

            $routeLocations.on('select2:select', function(e) {
                var selectedId = e.params.data.id;
                var $option = $(this).find('option[value="' + selectedId + '"]');
                $option.detach();
                $(this).append($option);
                $(this).trigger('change.select2');
            });

            // Auto-fill fields when hatchery is selected
            $('#hatchery_select').on('change', function() {
                let hatcheryId = $(this).val();

                if (!hatcheryId) {
                    // Clear all fields if no hatchery selected
                    $('#category_id').val('').trigger('change');
                    $('#vendor_id').val('').trigger('change');
                    $('#location_id').val('').trigger('change');
                    $('#branch_id').val([]).trigger('change');
                    $('#price').val('');
                    $('#broodstock_count').val('');
                    $('#description').val('');
                    $('#available_on').val('');
                    return;
                }

                // Fetch hatchery details
                $.get(`/admin/vehicle-availability/get-hatchery-details/${hatcheryId}`, function(response) {
                    if (response.success) {
                        // Set category
                        if (response.category_id) {
                            $('#category_id').val(response.category_id).trigger('change');
                        }

                        // Set vendor
                        if (response.vendor_id) {
                            $('#vendor_id').val(response.vendor_id).trigger('change');
                        }

                        // Set location
                        if (response.location_id) {
                            $('#location_id').val(response.location_id).trigger('change');
                        }

                        // Set price
                        if (response.price) {
                            $('#price').val(response.price);
                        }

                        // Set broodstock count
                        if (response.broodstock_count) {
                            $('#broodstock_count').val(response.broodstock_count);
                        }

                        // Set description
                        if (response.description) {
                            $('#description').val(response.description);
                        }

                        // Set available on date
                        if (response.available_on) {
                            $('#available_on').val(response.available_on);
                        }
                    }
                }).fail(function() {
                    console.error('Failed to fetch hatchery details');
                });
            });

            // Load branches when location changes
            $('#location_id').on('change', function() {
                let locationId = $(this).val();
                let branchSelect = $('#branch_id');

                if (!locationId) {
                    branchSelect.empty();
                    branchSelect.trigger('change');
                    return;
                }

                $.get(`/admin/get-branches/${locationId}`, function(data) {
                    branchSelect.empty();
                    data.forEach(b => {
                        branchSelect.append(
                            `<option value="${b.id}">${b.branch_name}</option>`);
                    });
                    branchSelect.trigger('change');
                });
            });

            // Gallery preview
            let selectedFiles = [];

            $('#gallery_input').on('change', function() {
                const files = Array.from(this.files);
                selectedFiles = selectedFiles.concat(files);
                updateGalleryPreview();
            });

            function updateGalleryPreview() {
                const previewContainer = $('#gallery_preview');
                previewContainer.empty();

                selectedFiles.forEach((file, index) => {
                    const isVideo = file.type.startsWith('video');
                    const reader = new FileReader();

                    reader.onload = function(e) {
                        const preview = $(`
                            <div class="gallery-preview-item" data-index="${index}">
                                ${isVideo
                                    ? `<video src="${e.target.result}" muted></video>`
                                    : `<img src="${e.target.result}" alt="Preview">`
                                }
                                <button type="button" class="remove-btn" data-index="${index}">&times;</button>
                                <span class="file-type-badge">${isVideo ? 'VIDEO' : 'IMAGE'}</span>
                            </div>
                        `);
                        previewContainer.append(preview);
                    };

                    reader.readAsDataURL(file);
                });

                // Update file input
                updateFileInput();
            }

            function updateFileInput() {
                const dt = new DataTransfer();
                selectedFiles.forEach(file => dt.items.add(file));
                $('#gallery_input')[0].files = dt.files;
            }

            $(document).on('click', '.gallery-preview-item .remove-btn', function() {
                const index = $(this).data('index');
                selectedFiles.splice(index, 1);
                updateGalleryPreview();
            });
        });
    </script>
@endpush
