@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-cogs mr-2"></i> Price Page Configuration
            </h3>
            @include('flash_msg')
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('market_prices.index') }}">Today Prices</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Disclaimer</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">
                            <i class="fas fa-edit mr-2"></i> Price Disclaimer Text
                        </h4>
                        <p class="text-muted mb-4">
                            This text is displayed below the seed prices in the mobile app. Update it to reflect the current pricing disclaimer.
                        </p>

                        <form action="{{ route('app-config.seed-price.update') }}" method="POST">
                            @csrf

                            <div class="form-group">
                                <label for="seed_price_disclaimer">Disclaimer Text</label>
                                <textarea
                                    name="seed_price_disclaimer"
                                    id="seed_price_disclaimer"
                                    class="form-control"
                                    rows="4"
                                    maxlength="1000"
                                    required
                                    placeholder="Enter the disclaimer text shown below seed prices..."
                                >{{ old('seed_price_disclaimer', $disclaimer) }}</textarea>
                                <small class="form-text text-muted">Maximum 1000 characters. This text appears with a warning icon below the price list in the app.</small>
                                @error('seed_price_disclaimer')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <hr>

                            <div class="form-group mb-0">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i> Save Disclaimer
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
                            <i class="fas fa-info-circle mr-2 text-info"></i> Current Disclaimer
                        </h5>
                        <hr>
                        <p>{{ $disclaimer ?: 'Not set' }}</p>
                        <hr>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-mobile-alt mr-1"></i>
                            This text is shown below the seed price list in the mobile app with a warning icon.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
