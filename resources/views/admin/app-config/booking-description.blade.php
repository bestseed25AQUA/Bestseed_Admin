@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-cogs mr-2"></i> Booking Description Configuration
            </h3>
            @include('flash_msg')
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Booking Description</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">
                            <i class="fas fa-edit mr-2"></i> Default Description Texts
                        </h4>
                        <p class="text-muted mb-4">
                            These texts are shown by default in the mobile app. If a specific booking has its own description (set via booking edit), that will override these defaults for that booking.
                        </p>

                        <form action="{{ route('app-config.booking-description.update') }}" method="POST">
                            @csrf

                            <div class="form-group">
                                <label for="default_booking_description">Default Booking Description</label>
                                <textarea
                                    name="default_booking_description"
                                    id="default_booking_description"
                                    class="form-control"
                                    rows="4"
                                    maxlength="1000"
                                    required
                                    placeholder="Enter the default booking description text..."
                                >{{ old('default_booking_description', $defaultBookingDescription) }}</textarea>
                                <small class="form-text text-muted">This text is shown in the Booking Detail screen under "Booking Status". Maximum 1000 characters.</small>
                                @error('default_booking_description')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="default_vehicle_description">Default Vehicle Description</label>
                                <textarea
                                    name="default_vehicle_description"
                                    id="default_vehicle_description"
                                    class="form-control"
                                    rows="4"
                                    maxlength="1000"
                                    required
                                    placeholder="Enter the default vehicle description text..."
                                >{{ old('default_vehicle_description', $defaultVehicleDescription) }}</textarea>
                                <small class="form-text text-muted">This text is shown in the Vehicle Tracking screen under "Vehicle Status". Maximum 1000 characters.</small>
                                @error('default_vehicle_description')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <hr>

                            <div class="form-group mb-0">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i> Save Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-info-circle mr-2 text-info"></i> How It Works
                        </h5>
                        <hr>
                        <p class="small"><strong>Booking Description:</strong> Shown in the booking detail screen. If you add a description for a specific booking (via booking edit page), it will override this default for that booking only.</p>
                        <p class="small"><strong>Vehicle Description:</strong> Shown in the vehicle tracking screen. If you add a vehicle description for a specific booking, it will override this default.</p>
                        <hr>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-mobile-alt mr-1"></i>
                            These defaults apply to all bookings that don't have their own custom description.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
