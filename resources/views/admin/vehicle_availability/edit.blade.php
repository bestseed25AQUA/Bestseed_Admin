@extends('admin.layouts.main')

@section('page_title', 'Edit Vehicle Availability')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Edit Vehicle Availability</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Vehicle Availability</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Update Vehicle Availability</h4>
                        @include('flash_msg')

                        <form method="POST" action="{{ route('vehicle-availability.update', $vehicleAvailability->id) }}"
                            enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            {{-- Row 1: Vehicle Name and Select Hatchery --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Vehicle Availability Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="vehicle_name" id="vehicle_name" class="form-control"
                                        value="{{ old('vehicle_name', $vehicleAvailability->vehicle_name) }}" required
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
                                        @foreach ($hatcheries as $h)
                                            <option value="{{ $h->id }}"
                                                {{ old('hatchery_id', $vehicleAvailability->hatchery_id) == $h->id ? 'selected' : '' }}>
                                                {{ $h->picker_label }}
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
                                                {{ old('category_id', $vehicleAvailability->category_id) == $category->id ? 'selected' : '' }}>
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
                                                {{ old('vendor_id', $vehicleAvailability->vendor_id) == $vendor->id ? 'selected' : '' }}>
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
                                                {{ old('location_id', $vehicleAvailability->location_id) == $loc->id ? 'selected' : '' }}>
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
                                                {{ in_array($branch->id, old('branch_id', $vehicleBranches ?? [])) ? 'selected' : '' }}>
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
                                    <textarea name="description" id="description" class="form-control" rows="3" placeholder="Enter description">{{ old('description', $vehicleAvailability->description) }}</textarea>
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
                                    @php
                                        $locationIds = old('location_ids', $vehicleAvailability->location_ids ?? []);
                                        $locationIds = is_array($locationIds) ? $locationIds : [];
                                        // Unselected locations first (in priority order), then selected ones in saved order
                                        $unselectedLocations = $locations->filter(fn($loc) => !in_array($loc->id, $locationIds));
                                        $selectedLocations = collect($locationIds)->map(fn($id) => $locations->firstWhere('id', $id))->filter();
                                    @endphp
                                    <select name="location_ids[]" class="form-control" id="route_locations" multiple="multiple"
                                        data-placeholder="Select locations in route order" required>
                                        @foreach ($unselectedLocations as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->location_name }}</option>
                                        @endforeach
                                        @foreach ($selectedLocations as $loc)
                                            <option value="{{ $loc->id }}" selected>{{ $loc->location_name }}</option>
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
                                        value="{{ old('start_date', $vehicleAvailability->start_date ? $vehicleAvailability->start_date->format('Y-m-d') : '') }}">
                                    @error('start_date')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" id="end_date" class="form-control"
                                        value="{{ old('end_date', $vehicleAvailability->end_date ? $vehicleAvailability->end_date->format('Y-m-d') : '') }}">
                                    @error('end_date')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Available Space</label>
                                    <input type="number" name="available_space" id="available_space"
                                        class="form-control"
                                        value="{{ old('available_space', $vehicleAvailability->available_space) }}"
                                        min="0" placeholder="Enter capacity">
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
                                        value="{{ old('available_on', $vehicleAvailability->available_on ? $vehicleAvailability->available_on->format('Y-m-d') : '') }}">
                                    @error('available_on')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Price</label>
                                    <input type="number" step="0.01" name="price" id="price"
                                        class="form-control" value="{{ old('price', $vehicleAvailability->price) }}"
                                        placeholder="Enter price">
                                    @error('price')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    @php
                                        // An expired vehicle is hidden from the app whatever is_active
                                        // says, so showing "Active" here contradicted both the listing
                                        // (which badges it Expired) and what the farmer actually sees.
                                        $effectiveActive = old(
                                            'is_active',
                                            $vehicleAvailability->is_expired ? 0 : $vehicleAvailability->is_active,
                                        );
                                    @endphp
                                    <select name="is_active" id="is_active" class="form-control">
                                        <option value="1" {{ $effectiveActive ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ !$effectiveActive ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @if ($vehicleAvailability->is_expired)
                                        <small class="text-muted d-block mt-1" id="expired_hint">
                                            Inactive because the end date
                                            ({{ $vehicleAvailability->end_date?->format('d M, Y') }}) has passed.
                                            Set a future end date to make it active again.
                                        </small>
                                    @endif
                                </div>
                            </div>

                            <!-- Vehicle Gallery Section -->
                            <div class="card border mt-4 mb-3">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-images mr-2"></i>Vehicle Gallery</h5>
                                </div>
                                <div class="card-body">
                                    <!-- Existing Gallery Items -->
                                    @if ($vehicleAvailability->gallery && $vehicleAvailability->gallery->count() > 0)
                                        <div class="mb-4">
                                            <label class="form-label">Current Gallery
                                                ({{ $vehicleAvailability->gallery->count() }} items)</label>
                                            <div id="existing_gallery" class="d-flex flex-wrap gap-2">
                                                @foreach ($vehicleAvailability->gallery as $item)
                                                    <div class="gallery-item" data-id="{{ $item->id }}">
                                                        @if ($item->isVideo())
                                                            <video src="{{ asset($item->file_path) }}" muted
                                                                class="gallery-media"></video>
                                                            <span class="file-type-badge">VIDEO</span>
                                                        @else
                                                            <img src="{{ asset($item->file_path) }}"
                                                                alt="{{ $item->original_name }}" class="gallery-media">
                                                            <span class="file-type-badge">IMAGE</span>
                                                        @endif
                                                        <button type="button" class="delete-gallery-btn"
                                                            data-id="{{ $item->id }}"
                                                            title="Delete this item">&times;</button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Upload New Gallery Items -->
                                    <div class="form-group">
                                        <label class="form-label">Add More Images & Videos</label>
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
                                <button type="submit" class="btn btn-primary me-2">Update</button>
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

        .gallery-item {
            position: relative;
            width: 120px;
            height: 120px;
        }

        .gallery-item .gallery-media {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .gallery-item .delete-gallery-btn {
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
            font-size: 14px;
            font-weight: bold;
            line-height: 1;
        }

        .gallery-item .file-type-badge {
            position: absolute;
            bottom: 4px;
            left: 4px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // Expired vehicles show Status = Inactive. If the admin extends the
            // end date into the future, flip it back to Active automatically —
            // otherwise saving would persist the "expired" Inactive and the
            // vehicle would stay hidden despite its new, valid date.
            $('#end_date').on('change', function() {
                var value = $(this).val();
                if (!value) return;

                var end = new Date(value + 'T23:59:59');
                var stillExpired = end < new Date();

                if (!stillExpired && $('#is_active').val() === '0') {
                    $('#is_active').val('1').trigger('change');
                    $('#expired_hint').remove();
                }
            });

            // Initialize Select2 for regular dropdowns
            $('.select2').select2({
                placeholder: function() {
                    return $(this).data('placeholder');
                },
                width: '100%',
                allowClear: true
            });

            // Route Locations: custom Select2 that preserves selection order
            var $routeLocations = $('#route_locations');
            $routeLocations.select2({
                placeholder: 'Select locations in route order',
                width: '100%',
                allowClear: true
            });

            // On page load: reorder options to match saved order
            var savedOrder = @json(array_map('strval', (array) ($vehicleAvailability->location_ids ?? [])));
            if (savedOrder.length > 0) {
                var $allOptions = $routeLocations.find('option').detach();
                savedOrder.forEach(function(id) {
                    $allOptions.filter('[value="' + id + '"]').prop('selected', true).appendTo($routeLocations);
                });
                $allOptions.filter(':not(:selected)').appendTo($routeLocations);
                $routeLocations.trigger('change.select2');
            }

            // On new selection: move selected option to end so tags render in click order
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
                let savedBranches = @json($vehicleBranches ?? []);

                if (!locationId) {
                    branchSelect.empty();
                    branchSelect.trigger('change');
                    return;
                }

                $.get(`/admin/get-branches/${locationId}`, function(data) {
                    branchSelect.empty();
                    data.forEach(b => {
                        let selected = savedBranches.includes(b.id) || savedBranches
                            .includes(String(b.id)) ? 'selected' : '';
                        branchSelect.append(
                            `<option value="${b.id}" ${selected}>${b.branch_name}</option>`
                            );
                    });
                    branchSelect.trigger('change');
                });
            });

            // Trigger branch load on page load if location is set
            @if ($vehicleAvailability->location_id)
                $('#location_id').trigger('change');
            @endif

            // Delete existing gallery item
            $(document).on('click', '.delete-gallery-btn', function() {
                const btn = $(this);
                const itemId = btn.data('id');
                const itemElement = btn.closest('.gallery-item');

                Swal.fire({
                    title: 'Delete this item?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '/admin/vehicle-gallery/' + itemId,
                            type: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                if (response.success) {
                                    itemElement.fadeOut(300, function() {
                                        $(this).remove();
                                        // Check if no more items
                                        if ($('#existing_gallery .gallery-item')
                                            .length === 0) {
                                            $('#existing_gallery').html(
                                                '<p class="text-muted">No gallery items</p>'
                                                );
                                        }
                                    });
                                    Swal.fire({
                                        toast: true,
                                        position: 'top-end',
                                        icon: 'success',
                                        title: 'Item deleted successfully',
                                        showConfirmButton: false,
                                        timer: 2000
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'Failed to delete item', 'error');
                            }
                        });
                    }
                });
            });

            // Gallery preview for new uploads
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
