@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-cogs mr-2"></i> Broodstock Configuration
            </h3>
            @include('flash_msg')
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('broad-stocks.index') }}">Brood Stock</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Configuration</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">
                            <i class="fas fa-filter mr-2"></i> Default Filter Settings
                        </h4>
                        <p class="text-muted mb-4">
                            Configure the default filter values that will be applied when users open the Broodstock screen in the mobile app.
                        </p>

                        <form action="{{ route('app-config.broodstock.update') }}" method="POST">
                            @csrf

                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Default Category</label>
                                <div class="col-sm-8">
                                    <select name="broodstock_default_category" class="form-control" required>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->category_name }}"
                                                {{ ($configs['broodstock_default_category'] ?? '') == $category->category_name ? 'selected' : '' }}>
                                                {{ $category->category_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">The category that will be selected by default</small>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Default Month</label>
                                <div class="col-sm-8">
                                    <select name="broodstock_default_month" class="form-control" required>
                                        @foreach($months as $value => $label)
                                            <option value="{{ $value }}"
                                                {{ ($configs['broodstock_default_month'] ?? '') == $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">The month that will be selected by default</small>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Default Year</label>
                                <div class="col-sm-8">
                                    <select name="broodstock_default_year" class="form-control" required>
                                        @foreach($years as $value => $label)
                                            <option value="{{ $value }}"
                                                {{ ($configs['broodstock_default_year'] ?? '') == $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">The year that will be selected by default</small>
                                </div>
                            </div>

                            <hr>

                            <div class="form-group row mb-0">
                                <div class="col-sm-8 offset-sm-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save mr-1"></i> Save Configuration
                                    </button>
                                    {{-- <a href="{{ route('broad-stocks.index') }}" class="btn btn-secondary ml-2">
                                        <i class="fas fa-arrow-left mr-1"></i> Back to Brood Stock
                                    </a> --}}
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-info-circle mr-2 text-info"></i> Current Settings
                        </h5>
                        <hr>
                        <p><strong>Category:</strong> {{ $configs['broodstock_default_category'] ?? 'Not set' }}</p>
                        <p><strong>Month:</strong> {{ $months[$configs['broodstock_default_month'] ?? ''] ?? 'Not set' }}</p>
                        <p><strong>Year:</strong> {{ $configs['broodstock_default_year'] ?? 'Not set' }}</p>
                        <hr>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-mobile-alt mr-1"></i>
                            When users open the Broodstock screen in the app, these filters will be applied automatically.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
