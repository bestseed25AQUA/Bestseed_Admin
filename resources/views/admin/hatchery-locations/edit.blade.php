@extends('admin.layouts.main')

@section('page_title', 'Edit Hatchery Location')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">Edit Hatchery Location</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Hatchery Locations</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Update Hatchery Location</h4>
                    @include('flash_msg')

                    <form class="forms-sample" method="POST" action="{{ route('hatchery-locations.update', $data->id) }}">
                        @csrf
                        @method('PUT')

                        {{-- Name and Priority --}}
                        <div class="row mb-3">
                            {{-- <div class="col-md-6">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control"
                                       value="{{ old('name', $data->name) }}" required>
                                @error('name')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div> --}}

                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <input type="number" name="priority" class="form-control"
                                       value="{{ old('priority', $data->priority) }}">
                                @error('priority')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        {{-- Google Maps Autocomplete --}}
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Search Location (Auto Detect Latitude & Longitude)</label>
                                <input type="text" id="location_search" class="form-control"
                                       placeholder="Enter location name or address"
                                       value="{{ old('location_name', $data->location_name) }}">
                                <input type="hidden" name="location_name" id="location_name_hidden"
                                       value="{{ old('location_name', $data->location_name) }}">
                            </div>
                        </div>

                        {{-- Latitude & Longitude --}}
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Latitude</label>
                                <input type="text" id="latitude" name="latitude" class="form-control"
                                       value="{{ old('latitude', $data->latitude) }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Longitude</label>
                                <input type="text" id="longitude" name="longitude" class="form-control"
                                       value="{{ old('longitude', $data->longitude) }}" readonly>
                            </div>
                        </div>

                        {{-- Submit Buttons --}}
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary me-2">Update</button>
                            <a href="{{ route('hatchery-locations.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- Google Maps Places API --}}
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=places"></script>

<script>
function initAutocomplete() {
    const input = document.getElementById("location_search");
    const hiddenInput = document.getElementById("location_name_hidden");
    const autocomplete = new google.maps.places.Autocomplete(input);

    autocomplete.addListener("place_changed", function () {
        const place = autocomplete.getPlace();

        if (!place.geometry) {
            alert("No details available for input: '" + place.name + "'");
            return;
        }

        const lat = place.geometry.location.lat();
        const lng = place.geometry.location.lng();

        document.getElementById("latitude").value = lat.toFixed(6);
        document.getElementById("longitude").value = lng.toFixed(6);
        hiddenInput.value = place.name; // Save location name
    });
}

// Initialize on page load
document.addEventListener("DOMContentLoaded", initAutocomplete);
</script>
@endpush
