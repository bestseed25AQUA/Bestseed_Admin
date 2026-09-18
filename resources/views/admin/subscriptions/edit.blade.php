@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-id-card mr-2"></i>Edit Subscription
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('subscriptions.index') }}">Subscriptions</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0 pl-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="alert alert-light border">
                            <strong>
                                {{ trim(($subscription->farmer->first_name ?? '') . ' ' . ($subscription->farmer->last_name ?? ''))
                                    ?: 'Farmer #' . $subscription->farmer_id }}
                            </strong>
                            · {{ $subscription->farmer->mobile ?? '—' }}
                        </div>

                        <form method="POST" action="{{ route('subscriptions.update', $subscription) }}">
                            @csrf
                            @method('PUT')

                            <div class="form-group">
                                <label>Package</label>
                                <select name="plan_key" class="form-control" required>
                                    @foreach ($plans as $key => $plan)
                                        <option value="{{ $key }}"
                                            {{ old('plan_key', $subscription->plan_key) === $key ? 'selected' : '' }}>
                                            {{ $plan['label'] }} — {{ $currency }}{{ number_format($plan['amount'], 0) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    Changing the package updates the recorded price and duration
                                    but not the dates below — set those yourself.
                                </small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Starts on</label>
                                    <input type="date" name="starts_at" class="form-control" required
                                           value="{{ old('starts_at', $subscription->starts_at?->toDateString()) }}">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Ends on</label>
                                    <input type="date" name="expires_at" class="form-control" required
                                           value="{{ old('expires_at', $subscription->expires_at?->toDateString()) }}">
                                    <small class="form-text text-muted">Inclusive — access lasts all of this day.</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" rows="3" class="form-control">{{ old('notes', $subscription->notes) }}</textarea>
                            </div>

                            <div class="alert alert-warning">
                                Saving clears any expiry reminders already sent for this
                                subscription, so the farmer is warned again against the new
                                dates. Without that, extending a subscription would leave every
                                reminder marked done and the farmer would hear nothing.
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check mr-1"></i> Save Changes
                            </button>
                            <a href="{{ route('subscriptions.index') }}" class="btn btn-light">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
