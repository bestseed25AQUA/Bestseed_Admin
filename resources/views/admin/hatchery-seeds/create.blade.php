@extends('admin.layouts.main')

@section('page_title', 'Create Hatchery Seed')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Create Hatchery Seed</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('hatchery-seeds.index') }}">Hatchery Seeds</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Add New Seed</h4>
                        @include('flash_msg')

                        <form method="POST" action="{{ route('hatchery-seeds.store') }}">
                            @csrf

                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Hatchery Name</label>
                                    <select name="hatchery_id" class="form-control">
                                        <option value="">Select Hatcheries</option>
                                        @foreach ($data as $hatchery)
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

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Seed Brand</label>
                                    <input type="text" name="seed_brand" class="form-control"
                                        value="{{ old('seed_brand') }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" class="form-control">
                                        <option value="">Select Category</option>
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
                                    <label class="form-label">Location</label>
                                    <select name="location_id" class="form-control">
                                        <option value="">Select Location</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}"
                                                {{ old('location_id') == $location->id ? 'selected' : '' }}>
                                                {{ $location->location_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('location_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Brood Stock</label>
                                    <input type="number" name="broad_stock" class="form-control"
                                        value="{{ old('broad_stock') }}">
                                </div>

                                {{-- <div class="col-md-6 mb-3">
                                <label class="form-label">Brood Stock</label>
                                <input type="number" name="brood_stock" class="form-control" value="{{ old('brood_stock') }}">
                            </div> --}}

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Count</label>
                                    <input type="number" name="count" class="form-control" value="{{ old('count') }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Price per Piece (₹)</label>
                                    <input type="number" step="0.01" name="price_per_piece" class="form-control"
                                        value="{{ old('price_per_piece') }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Stock Available Date</label>
                                    <input type="date" name="stock_available_date" class="form-control"
                                        value="{{ old('stock_available_date') }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Packing Start Date</label>
                                    <input type="date" name="packing_start_date" class="form-control"
                                        value="{{ old('packing_start_date') }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Expire Date</label>
                                    <input type="date" name="expire_date" class="form-control"
                                        value="{{ old('expire_date') }}">
                                </div>

                                {{-- <div class="col-md-6 mb-3">
                                <label class="form-label">Delivery Location</label>
                                <input type="text" id="delivery_location_input" class="form-control" placeholder="Enter delivery location">
                                <input type="hidden" name="delivery_location" id="delivery_location" value="{{ old('delivery_location') }}">
                            </div> --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pincode</label>
                                    <input type="text" name="pincode" class="form-control" value="{{ old('pincode') }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Upload Image</label>

                                    <input type="file" name="image_path" class="filepond" accept="image/png, image/jpeg"
                                        @if (!empty($model->image_path)) data-existing="{{ asset('storage/' . $model->image_path) }}" @endif>


                                    @error('image_path')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>


                                {{-- <div class="col-md-6 mb-3">
                                <label class="form-label">Report Image Path</label>
                                <input type="text" name="report_image_path" class="form-control" value="{{ old('report_image_path') }}">
                            </div> --}}

                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control sun-editor" rows="3">{{ old('description') }}</textarea>
                                </div>

                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Create</button>
                                <a href="{{ route('hatchery-seeds.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- Google Maps Places API -->
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=places">
    </script>
    <script>
        function initAutocomplete() {
            const input = document.getElementById('delivery_location_input');
            const hiddenInput = document.getElementById('delivery_location');
            const autocomplete = new google.maps.places.Autocomplete(input);

            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (!place.geometry) {
                    alert("No details available for input: '" + place.name + "'");
                    return;
                }
                hiddenInput.value = place.name;
            });
        }
        document.addEventListener('DOMContentLoaded', initAutocomplete);
    </script>
@endpush
