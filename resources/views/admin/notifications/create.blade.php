@extends('admin.layouts.main')

@section('page_title', 'Send Push Notification')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Send Push Notification</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.notifications.index') }}">Push Notifications</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Send New</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">New Notification</h4>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.notifications.store') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="row">
                                <!-- Recipient Type -->
                                <div class="col-md-12 mb-3">
                                    <label class="form-label font-weight-bold">Send To <span class="text-danger">*</span></label>
                                    <div class="d-flex mt-2" style="gap: 40px; margin-left: 20px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recipient_type" id="recipientAll" value="all" {{ old('recipient_type', 'all') === 'all' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="recipientAll">All Users</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recipient_type" id="recipientSelected" value="selected" {{ old('recipient_type') === 'selected' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="recipientSelected">Selected Users</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Farmer Select (shown when "Selected Users" is chosen) -->
                                <div class="col-md-12 mb-3" id="farmerSelectWrapper" style="display: none;">
                                    <label class="form-label">Select Users <span class="text-danger">*</span></label>
                                    <select name="farmer_ids[]" id="farmerSelect" class="form-control" multiple="multiple" style="width: 100%;">
                                    </select>
                                    <small class="text-muted">Search by name, mobile number, or location</small>
                                    @error('farmer_ids')
                                        <br><span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Title -->
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required placeholder="e.g. Fresh Arrival: New Shrimp Varieties">
                                    @error('title')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Module / Type -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Module / Type (Optional)</label>
                                    <select name="module" class="form-control">
                                        <option value="">-- Select Module --</option>
                                        @foreach([
                                            'Best Deals',
                                            'Broodstock',
                                            'Category',
                                            'Climate News',
                                            'Farm Management',
                                            'Hatchery',
                                            'Hatchery Updates',
                                            'Medicine News',
                                            'Seed Request',
                                            'Wanted Stock',
                                            'Spot Hatchery',
                                            "Today's Market Prices",
                                            'Trending Updates',
                                            'Vehicle Availability',
                                        ] as $module)
                                            <option value="{{ $module }}" {{ old('module') === $module ? 'selected' : '' }}>{{ $module }}</option>
                                        @endforeach
                                    </select>
                                    @error('module')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Specific Hatchery (shown only when Module = Hatchery) -->
                                <div class="col-md-6 mb-3" id="hatcherySelectWrapper" style="display: none;">
                                    <label class="form-label">Specific Hatchery (Optional)</label>
                                    <select name="module_id" id="hatcherySelect" class="form-control" style="width: 100%;">
                                        <option value="">-- General Hatchery list --</option>
                                    </select>
                                    <small class="text-muted">Pick a hatchery to open its details page directly. Leave empty to open the general list.</small>
                                    @error('module_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Category (auto-filled from the selected hatchery; editable) -->
                                <div class="col-md-6 mb-3" id="categorySelectWrapper" style="display: none;">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" id="categorySelect" class="form-control" style="width: 100%;">
                                        <option value="">-- Select Category --</option>
                                        @foreach(($categories ?? []) as $category)
                                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->category_name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Auto-filled from the hatchery. Change it to override what gets saved.</small>
                                    @error('category_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Image -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Image (Optional)</label>
                                    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/jpg,image/webp" id="imageInput">
                                    <small class="text-muted">Max 2MB. JPEG, PNG, JPG, WebP.</small>
                                    @error('image')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Image Preview -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Preview</label>
                                    <div>
                                        <img id="imagePreview" src="" alt="Preview" style="max-height: 120px; display: none;" class="img-thumbnail">
                                    </div>
                                </div>

                                <!-- Body / Description -->
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Description / Body (Optional)</label>
                                    <textarea name="body" class="form-control" rows="5" placeholder="Write the notification message here...">{{ old('body') }}</textarea>
                                    @error('body')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2" id="submitBtn">
                                    <i class="fas fa-paper-plane mr-1"></i> Send to All Users
                                </button>
                                <a href="{{ route('admin.notifications.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>

                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                // Image preview
                                document.getElementById('imageInput').addEventListener('change', function(e) {
                                    const file = e.target.files[0];
                                    const preview = document.getElementById('imagePreview');
                                    if (file) {
                                        const reader = new FileReader();
                                        reader.onload = function(event) {
                                            preview.src = event.target.result;
                                            preview.style.display = 'block';
                                        };
                                        reader.readAsDataURL(file);
                                    } else {
                                        preview.style.display = 'none';
                                    }
                                });

                                // Recipient type toggle
                                const allRadio = document.getElementById('recipientAll');
                                const selectedRadio = document.getElementById('recipientSelected');
                                const farmerWrapper = document.getElementById('farmerSelectWrapper');
                                const submitBtn = document.getElementById('submitBtn');

                                function toggleFarmerSelect() {
                                    if (selectedRadio.checked) {
                                        farmerWrapper.style.display = 'block';
                                        submitBtn.innerHTML = '<i class="fas fa-paper-plane mr-1"></i> Send to Selected Users';
                                    } else {
                                        farmerWrapper.style.display = 'none';
                                        submitBtn.innerHTML = '<i class="fas fa-paper-plane mr-1"></i> Send to All Users';
                                    }
                                }

                                allRadio.addEventListener('change', toggleFarmerSelect);
                                selectedRadio.addEventListener('change', toggleFarmerSelect);
                                toggleFarmerSelect(); // Initial state

                                // Module → show the hatchery picker only for "Hatchery"
                                var moduleSelect = document.querySelector('select[name="module"]');
                                var hatcheryWrapper = document.getElementById('hatcherySelectWrapper');
                                var categoryWrapper = document.getElementById('categorySelectWrapper');

                                function toggleHatcherySelect() {
                                    if (moduleSelect.value === 'Hatchery') {
                                        hatcheryWrapper.style.display = 'block';
                                        // Category only makes sense once a hatchery is picked.
                                        toggleCategorySelect();
                                    } else {
                                        hatcheryWrapper.style.display = 'none';
                                        categoryWrapper.style.display = 'none';
                                        // Clear any chosen hatchery + category when leaving Hatchery
                                        $('#hatcherySelect').val('').trigger('change');
                                        $('#categorySelect').val('').trigger('change');
                                    }
                                }
                                moduleSelect.addEventListener('change', toggleHatcherySelect);

                                // Show the category dropdown only when a hatchery is selected.
                                function toggleCategorySelect() {
                                    if (moduleSelect.value === 'Hatchery' && $('#hatcherySelect').val()) {
                                        categoryWrapper.style.display = 'block';
                                    } else {
                                        categoryWrapper.style.display = 'none';
                                    }
                                }

                                // Searchable category dropdown
                                $('#categorySelect').select2({
                                    placeholder: 'Select category...',
                                    allowClear: true,
                                    width: '100%'
                                });

                                // Searchable hatchery dropdown.
                                // minimumInputLength: 0 → opening the dropdown shows the
                                // full hatchery list immediately (browse without typing);
                                // typing filters it. Pagination drives infinite scroll so
                                // the COMPLETE list is reachable, not just the first page.
                                $('#hatcherySelect').select2({
                                    placeholder: 'Search or pick a hatchery from the list...',
                                    allowClear: true,
                                    width: '100%',
                                    minimumInputLength: 0,
                                    ajax: {
                                        url: '{{ route("admin.notifications.search-hatcheries") }}',
                                        dataType: 'json',
                                        delay: 300,
                                        data: function(params) {
                                            return { q: params.term, page: params.page || 1 };
                                        },
                                        processResults: function(data, params) {
                                            params.page = params.page || 1;
                                            return {
                                                results: data.results || [],
                                                pagination: {
                                                    more: data.pagination ? data.pagination.more : false
                                                }
                                            };
                                        },
                                        cache: true
                                    }
                                });

                                // Auto-fill the category from the chosen hatchery (admin can still change it).
                                $('#hatcherySelect').on('select2:select', function(e) {
                                    var categoryId = e.params.data.category_id;
                                    categoryWrapper.style.display = 'block';
                                    if (categoryId) {
                                        $('#categorySelect').val(String(categoryId)).trigger('change');
                                    } else {
                                        $('#categorySelect').val('').trigger('change');
                                    }
                                });

                                // Clearing the hatchery hides + resets the category.
                                $('#hatcherySelect').on('select2:clear change', function() {
                                    if (!$(this).val()) {
                                        categoryWrapper.style.display = 'none';
                                        $('#categorySelect').val('').trigger('change');
                                    }
                                });

                                toggleHatcherySelect(); // Initial state (handles old('module'))

                                // Store latest search results for "Select All"
                                var latestSearchResults = [];

                                // Initialize Select2 with AJAX search and checkboxes
                                $('#farmerSelect').select2({
                                    placeholder: 'Search users...',
                                    allowClear: true,
                                    minimumInputLength: 1,
                                    closeOnSelect: false,
                                    ajax: {
                                        url: '{{ route("admin.notifications.search-farmers") }}',
                                        dataType: 'json',
                                        delay: 300,
                                        data: function(params) {
                                            return { q: params.term };
                                        },
                                        processResults: function(data) {
                                            latestSearchResults = data.results || [];
                                            // Add "Select All" option at top
                                            var results = [{ id: '_select_all', text: 'Select All (' + latestSearchResults.length + ' users)' }];
                                            return { results: results.concat(latestSearchResults) };
                                        },
                                        cache: false
                                    },
                                    templateResult: function(state) {
                                        if (!state.id) return state.text;

                                        // "Select All" option styling
                                        if (state.id === '_select_all') {
                                            return $('<span class="select-all-option"><i class="fas fa-check-double mr-2"></i>' + state.text + '</span>');
                                        }

                                        return $('<span class="farmer-option">' + state.text + '</span>');
                                    }
                                });

                                // Handle "Select All" click
                                $('#farmerSelect').on('select2:select', function(e) {
                                    if (e.params.data.id === '_select_all') {
                                        // Remove the _select_all option from the hidden select
                                        $('#farmerSelect option[value="_select_all"]').remove();

                                        // Add all search results that aren't already selected
                                        var existingVals = $(this).val() || [];
                                        latestSearchResults.forEach(function(item) {
                                            var idStr = item.id.toString();
                                            // Only add if not already in the select
                                            if (!$('#farmerSelect option[value="' + idStr + '"]').length) {
                                                var option = new Option(item.text, idStr, true, true);
                                                $('#farmerSelect').append(option);
                                            } else {
                                                // Already exists as option, just mark selected
                                                $('#farmerSelect option[value="' + idStr + '"]').prop('selected', true);
                                            }
                                        });
                                        $('#farmerSelect').trigger('change');

                                        // Close and reopen dropdown so checkboxes refresh
                                        $('#farmerSelect').select2('close');
                                        $('#farmerSelect').select2('open');
                                    }
                                });
                            });
                        </script>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    /* Make Select2 input full width and visible placeholder */
    #farmerSelectWrapper .select2-container {
        width: 100% !important;
    }
    #farmerSelectWrapper .select2-container--default .select2-selection--multiple {
        min-height: 42px;
        border: 1px solid #ced4da;
        padding: 4px 8px;
    }
    #farmerSelectWrapper .select2-container--default .select2-selection--multiple .select2-selection__placeholder {
        color: #999;
    }
    /* Checkbox style in dropdown items */
    .select2-results__option {
        padding: 8px 12px !important;
        padding-left: 36px !important;
        position: relative;
    }
    /* Unchecked checkbox */
    .select2-results__option .farmer-option::before {
        content: '\f0c8';
        font-family: 'Font Awesome 5 Free';
        font-weight: 400;
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 16px;
        color: #999;
    }
    /* Checked checkbox (ticked) */
    .select2-results__option[aria-selected="true"] .farmer-option::before {
        content: '\f14a';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        color: #007bff;
    }
    .select2-results__option[aria-selected="true"] {
        background-color: #e8f0fe !important;
        color: #333 !important;
    }
    /* Select All option styling */
    .select2-results__option .select-all-option {
        font-weight: bold;
        color: #007bff;
    }
    .select2-results__option .select-all-option::before {
        content: none !important;
    }
    .select2-results__option:has(.select-all-option) {
        padding-left: 12px !important;
        border-bottom: 1px solid #eee;
    }
</style>
@endpush
