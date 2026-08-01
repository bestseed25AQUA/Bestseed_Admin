@extends('admin.layouts.main')

@section('page_title', 'Edit Spot Hatchery')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Edit Spot Hatchery</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Spot Hatchery</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Update Spot Hatchery</h4>
                        @include('flash_msg')

                        <form method="POST" action="{{ route('spot-hatcheries.update', $hatchery->id) }}"
                            enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            {{-- Row 1: Spot Hatchery Name and Select Hatchery --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Spot Hatchery Name <span class="text-danger">*</span></label>
                                    <input type="text" name="hatchery_name" id="hatchery_name" class="form-control"
                                        value="{{ old('hatchery_name', $hatchery->hatchery_name) }}" required>
                                    @error('hatchery_name')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Select Hatchery (Auto-fill details)</label>
                                    <select name="selected_hatchery_id" id="hatchery_select" class="form-control select2"
                                        data-placeholder="Search and select a hatchery">
                                        <option value="">Choose Hatchery</option>
                                        @foreach ($hatcheries as $h)
                                            <option value="{{ $h->id }}"
                                                {{ old('selected_hatchery_id', $hatchery->selected_hatchery_id) == $h->id ? 'selected' : '' }}>
                                                {{ $h->hatchery_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('selected_hatchery_id')
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
                                        @foreach ($categories as $cat)
                                            <option value="{{ $cat->id }}"
                                                {{ old('category_id', $hatchery->category_id) == $cat->id ? 'selected' : '' }}>
                                                {{ $cat->category_name }}
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

                            {{-- Row 3: Location and Branch --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Location <span class="text-danger">*</span></label>
                                    <select name="location_id" id="location_id" class="form-control select2"
                                        data-placeholder="Select location" required>
                                        <option value="">Choose Location</option>
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
                                <div class="col-md-6">
                                    <label class="form-label">Branch</label>
                                    <select name="branch_id[]" id="branch_id" class="form-control select2" multiple="multiple"
                                        data-placeholder="Select branches">
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}"
                                                {{ in_array($branch->id, old('branch_id', $hatcheryBranches ?? [])) ? 'selected' : '' }}>
                                                {{ $branch->branch_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('branch_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Row 4: Price and Broodstock Count --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Price</label>
                                    <input type="number" step="0.01" name="price" id="price" class="form-control"
                                        value="{{ old('price', $hatchery->price) }}" placeholder="Enter price">
                                    @error('price')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Broodstock Count</label>
                                    <input type="number" name="broodstock_count" id="broodstock_count" class="form-control"
                                        value="{{ old('broodstock_count', $hatchery->broodstock_count) }}" placeholder="Enter broodstock count">
                                    @error('broodstock_count')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Row 4b: No of Pieces --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">No of Pieces</label>
                                    <input type="number" name="no_of_pieces" id="no_of_pieces" class="form-control"
                                        value="{{ old('no_of_pieces', $hatchery->no_of_pieces) }}" placeholder="Enter number of pieces">
                                    @error('no_of_pieces')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Row 5: Description --}}
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="description" class="form-control" rows="3"
                                        placeholder="Enter description">{{ old('description', $hatchery->description) }}</textarea>
                                    @error('description')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Row 6: Available On --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Available On</label>
                                    <input type="date" name="available_on" id="available_on" class="form-control"
                                        value="{{ old('available_on', $hatchery->available_on) }}">
                                    @error('available_on')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-control">
                                        <option value="active" {{ old('status', $hatchery->status) == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status', $hatchery->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Existing Images/Videos --}}
                            @php
                                // Get raw value from database, not the accessor
                                $rawImageValue = $hatchery->getRawOriginal('image');
                                $images = [];
                                if (!empty($rawImageValue)) {
                                    $decoded = json_decode($rawImageValue, true);
                                    if (is_array($decoded)) {
                                        $images = $decoded;
                                    } elseif (!empty($rawImageValue)) {
                                        $images = [$rawImageValue];
                                    }
                                }
                            @endphp

                            @if (!empty($images))
                                <div class="mb-3">
                                    <label class="form-label">Existing Images/Videos</label>
                                    <div class="d-flex flex-wrap gap-2" id="existing-media">
                                        @foreach ($images as $img)
                                            @php
                                                $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
                                                $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'webm']);
                                            @endphp
                                            <div class="media-item position-relative" style="width: 100px;">
                                                @if ($isVideo)
                                                    <video src="{{ asset($img) }}" width="100" height="80" class="rounded" controls></video>
                                                @else
                                                    <img src="{{ asset($img) }}" alt="Hatchery Image" width="100" height="80" class="rounded" style="object-fit: cover;">
                                                @endif
                                                <button type="button" class="btn btn-sm btn-danger position-absolute" style="top: -5px; right: -5px; padding: 2px 6px; font-size: 10px;" onclick="removeMedia(this, '{{ $img }}')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                    <input type="hidden" name="removed_images" id="removed_images" value="">
                                </div>
                            @endif

                            {{-- Upload New Images/Videos --}}
                            <div class="mb-3">
                                <label class="form-label">Upload Spot Hatchery Images/Videos (PNG, JPG, MP4, MOV)</label>
                                <input type="file" name="image[]" class="form-control" multiple
                                    accept="image/png,image/jpeg,image/jpg,video/mp4,video/quicktime,video/x-msvideo">
                                <small class="text-muted">Max file size: 10MB for images, 50MB for videos</small>
                                @error('image')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Update</button>
                                <a href="{{ route('spot-hatcheries.index') }}" class="btn btn-light">Cancel</a>
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

        .media-item {
            display: inline-block;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        var removedImages = [];

        function removeMedia(btn, path) {
            removedImages.push(path);
            $('#removed_images').val(JSON.stringify(removedImages));
            $(btn).closest('.media-item').fadeOut(300, function() {
                $(this).remove();
            });
        }

        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2({
                placeholder: function() {
                    return $(this).data('placeholder');
                },
                width: '100%',
                allowClear: true
            });

            // Auto-fill fields when hatchery is selected
            $('#hatchery_select').on('change', function() {
                let hatcheryId = $(this).val();

                if (!hatcheryId) {
                    return;
                }

                // Fetch hatchery details
                $.get(`/admin/spot-hatcheries/get-hatchery-details/${hatcheryId}`, function(response) {
                    if (response.success) {
                        let data = response.data;

                        // Set category
                        if (data.category_id) {
                            $('#category_id').val(data.category_id).trigger('change');
                        }

                        // Set vendor
                        if (data.vendor_id) {
                            $('#vendor_id').val(data.vendor_id).trigger('change');
                        }

                        // Set location
                        if (data.location_id) {
                            $('#location_id').val(data.location_id).trigger('change');

                            // Load branches for this location after a short delay
                            setTimeout(function() {
                                if (data.branch_id) {
                                    $('#branch_id').val(data.branch_id).trigger('change');
                                }
                            }, 500);
                        }

                        // Set price
                        if (data.price) {
                            $('#price').val(data.price);
                        }

                        // Set broodstock count
                        if (data.broodstock_count) {
                            $('#broodstock_count').val(data.broodstock_count);
                        }

                        // Set description
                        if (data.description) {
                            $('#description').val(data.description);
                        }

                        // Set available on date
                        if (data.available_on) {
                            $('#available_on').val(data.available_on);
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
                let savedBranches = @json($hatcheryBranches ?? []);

                if (!locationId) {
                    branchSelect.empty();
                    branchSelect.trigger('change');
                    return;
                }

                $.get(`/admin/get-branches/${locationId}`, function(data) {
                    branchSelect.empty();
                    data.forEach(b => {
                        let selected = savedBranches.includes(b.id) || savedBranches.includes(String(b.id)) ? 'selected' : '';
                        branchSelect.append(`<option value="${b.id}" ${selected}>${b.branch_name}</option>`);
                    });
                    branchSelect.trigger('change');
                });
            });

            // Trigger branch load on page load if location is set
            @if($hatchery->location_id)
                $('#location_id').trigger('change');
            @endif
        });
    </script>
@endpush
