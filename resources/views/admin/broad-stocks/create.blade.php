@extends('admin.layouts.main')

@section('page_title', 'Create Broad Stock')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">Create Broad Stock</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('broad-stocks.index') }}">Broad Stock</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Add Broad Stock</h4>
                    @include('flash_msg')

                    <form class="forms-sample" method="POST" action="{{ route('broad-stocks.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row mb-3">

                             <div class="col-md-6">
                                <label class="form-label">Hatchery Name</label>
                                <select name="hatchery_id" id="hatchery_select" class="form-control">
                                    <option value="">Select Hatcheries</option>
                                    @foreach($data as $hatchery)
                                        <option value="{{ $hatchery->id }}" data-category="{{ $hatchery->category_id }}" data-location="{{ $hatchery->location_id }}" {{ old('hatchery_id') == $hatchery->id ? 'selected' : '' }}>
                                            {{ $hatchery->picker_label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('hatchery_id')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            {{-- <div class="col-md-6">
                                <label class="form-label">Hatchery Name</label>
                                <input type="text" name="hatchery_name" class="form-control" value="{{ old('hatchery_name') }}">
                                @error('hatchery_name')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div> --}}

                            <div class="col-md-6">
                                <label class="form-label">Brood Stock Category</label>
                                <select name="category_id" class="form-control">
                                    <option value="">Select Category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->category_name  }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Supplier Category</label>
                                <select name="supplier_name" id="supplier_category" class="form-control">
                                    <option value="">Select Supplier Category</option>
                                    @foreach($supplierCategories as $sc)
                                        <option value="{{ $sc->category_name }}" data-category-id="{{ $sc->id }}" {{ old('supplier_name') == $sc->category_name ? 'selected' : '' }}>
                                            {{ $sc->category_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('supplier_name')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Imported Date</label>
                                <input type="date" name="imported_date" class="form-control" value="{{ old('imported_date') }}">
                                @error('imported_date')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <select name="location_id" class="form-control">
                                    <option value="">Select Location</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>
                                            {{ $location->location_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('location_id')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Count</label>
                                <input type="number" name="count" class="form-control" value="{{ old('count') }}">
                                @error('count')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Available Date</label>
                                <input type="date" name="available_date" class="form-control" value="{{ old('available_date') }}">
                                @error('available_date')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">Select Status</option>
                                    <option value="1" {{ old('status') == 1 ? 'selected' : '' }}>Available</option>
                                    <option value="2" {{ old('status') == 2 ? 'selected' : '' }}>Coming Soon</option>
                                    <option value="3" {{ old('status') == 3 ? 'selected' : '' }}>Upcoming</option>
                                    <option value="4" {{ old('status') == 4 ? 'selected' : '' }}>Shortly Available</option>
                                    <option value="5" {{ old('status') == 5 ? 'selected' : '' }}>Closed</option>
                                </select>
                                @error('status')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Enter description">{{ old('description') }}</textarea>
                                @error('description')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        {{-- <div class="mb-3">
                            <label class="form-label">Seed Image (PNG, JPG, JPEG)</label>
                            <input type="file" name="seed_image" class="form-control">
                            @error('seed_image')
                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div> --}}
                        {{-- <div class="mb-3">
                            <label class="form-label">Seed Image (PNG, JPG, JPEG)</label>
                            <input type="file"
                                name="seed_image"
                                class="filepond"
                                accept="image/png, image/jpeg, image/jpg">
                            @error('seed_image')
                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div> --}}


                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary me-2">Create</button>
                            <a href="{{ route('broad-stocks.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('hatchery_select').addEventListener('change', function() {
        var selected = this.options[this.selectedIndex];
        var categoryId = selected.getAttribute('data-category') || '';
        var locationId = selected.getAttribute('data-location') || '';

        var locationSelect = document.querySelector('select[name="location_id"]');

        if (locationId) locationSelect.value = locationId;

        // Auto-fill Supplier Category from hatchery's category
        var supplierSelect = document.getElementById('supplier_category');
        if (categoryId) {
            var options = supplierSelect.options;
            for (var i = 0; i < options.length; i++) {
                if (options[i].getAttribute('data-category-id') === categoryId) {
                    supplierSelect.value = options[i].value;
                    break;
                }
            }
        } else {
            supplierSelect.value = '';
        }
    });
</script>
@endpush
