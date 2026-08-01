@extends('admin.layouts.main')

@section('page_title', 'Create Hatchery Location')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">Create Hatchery Location</h3>
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
                    <h4 class="card-title">Add New Hatchery Location</h4>
                    @include('flash_msg')

                    <form class="forms-sample" method="POST" action="{{ route('hatchery-locations.store') }}">
                        @csrf

                        <div class="row mb-3">
                            {{-- Name --}}
                            {{-- <div class="col-md-6">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                                @error('name')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div> --}}

                            {{-- Priority --}}
                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <input type="number" name="priority" class="form-control" value="{{ old('priority') }}">
                                @error('priority')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        {{-- Google Autocomplete Location --}}
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Select Location</label>
                                <input type="text" id="location_input" class="form-control" placeholder="Enter location" />
                                <input type="hidden" name="location_name" id="location_name">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Latitude</label>
                                <input type="text" id="latitude" name="latitude" class="form-control" readonly>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Longitude</label>
                                <input type="text" id="longitude" name="longitude" class="form-control" readonly>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary me-2">Create</button>
                            <a href="{{ route('hatchery-locations.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    #location_input {
        border-radius: 6px;
        padding: 10px;
    }
</style>
@endpush

@push('scripts')
<!-- Google Maps Places API -->
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=places"></script>

<script>
function initAutocomplete() {
    const input = document.getElementById('location_input');
    const autocomplete = new google.maps.places.Autocomplete(input);

    autocomplete.addListener('place_changed', function () {
        const place = autocomplete.getPlace();
        if (!place.geometry) {
            alert("No details available for input: '" + place.name + "'");
            return;
        }

        const lat = place.geometry.location.lat();
        const lng = place.geometry.location.lng();
        const locationName = place.name;

        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
        document.getElementById('location_name').value = locationName; // Save location name
    });
}

document.addEventListener('DOMContentLoaded', initAutocomplete);
</script>
@endpush
