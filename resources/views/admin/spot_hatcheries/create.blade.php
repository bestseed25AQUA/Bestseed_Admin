@extends('admin.layouts.main')

@section('page_title', 'Register Spot Hatchery')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Register Spot Hatchery</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create Spot Hatchery</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Create Spot Hatchery</h4>
                        @include('flash_msg')

                        <form method="POST" action="{{ route('spot-hatcheries.store') }}" enctype="multipart/form-data">
                            @csrf

                            {{-- Row 1: Spot Hatchery Name and Select Hatchery --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Spot Hatchery Name <span class="text-danger">*</span></label>
                                    <input type="text" name="hatchery_name" id="hatchery_name" class="form-control"
                                        value="{{ old('hatchery_name') }}" required>
                                    @error('hatchery_name')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Select Hatchery (Auto-fill details)</label>
                                    <select name="selected_hatchery_id" id="hatchery_select" class="form-control select2"
                                        data-placeholder="Search and select a hatchery">
                                        <option value="">Choose Hatchery</option>
                                        @foreach ($hatcheries as $hatchery)
                                            <option value="{{ $hatchery->id }}"
                                                {{ old('selected_hatchery_id') == $hatchery->id ? 'selected' : '' }}>
                                                {{ $hatchery->hatchery_name }}
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
                                                {{ old('category_id') == $cat->id ? 'selected' : '' }}>
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
                                    <select name="branch_id[]" id="branch_id" class="form-control select2" multiple="multiple"
                                        data-placeholder="Select branches">
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

                            {{-- Row 4: Price and Broodstock Count --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Price</label>
                                    <input type="number" step="0.01" name="price" id="price" class="form-control"
                                        value="{{ old('price') }}" placeholder="Enter price">
                                    @error('price')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Broodstock Count</label>
                                    <input type="number" name="broodstock_count" id="broodstock_count" class="form-control"
                                        value="{{ old('broodstock_count') }}" placeholder="Enter broodstock count">
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
                                        value="{{ old('no_of_pieces') }}" placeholder="Enter number of pieces">
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
                                        placeholder="Enter description">{{ old('description') }}</textarea>
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
                                        value="{{ old('available_on') }}">
                                    @error('available_on')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-control">
                                        <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Row 7: Upload Images/Videos --}}
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
                                <button type="submit" class="btn btn-primary me-2">Register</button>
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
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
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

                if (!locationId) {
                    branchSelect.empty();
                    branchSelect.trigger('change');
                    return;
                }

                $.get(`/admin/get-branches/${locationId}`, function(data) {
                    branchSelect.empty();
                    data.forEach(b => {
                        branchSelect.append(`<option value="${b.id}">${b.branch_name}</option>`);
                    });
                    branchSelect.trigger('change');
                });
            });
        });
    </script>
@endpush
