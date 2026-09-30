@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title d-flex align-items-center">
            <i class="fas fa-rotate mr-2"></i> Renew Subscription
        </h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('subscriptions.index') }}">Subscriptions</a></li>
                <li class="breadcrumb-item active" aria-current="page">Renew</li>
            </ol>
        </nav>
    </div>

    @include('admin.partials.flash')

    <div class="card">
        <div class="card-body">
            <p class="mb-3">
                Renewing <strong>{{ trim(($subscription->farmer->first_name ?? '') . ' ' . ($subscription->farmer->last_name ?? '')) ?: 'Farmer #' . $subscription->farmer_id }}</strong>
                <span class="text-muted">· {{ $subscription->farmer->mobile ?? '' }}</span>
            </p>

            {{-- The old term is left exactly as sold. A renewal is a second
                 purchase, and the history should say so. --}}
            <div class="alert alert-light border py-2 px-3 small">
                Current term: <strong>{{ $subscription->plan_label }}</strong>
                — {{ $subscription->farm_limit }} {{ Str::plural('farm', $subscription->farm_limit) }},
                {{ $subscription->starts_at->format('d M Y') }} to {{ $subscription->expires_at->format('d M Y') }}
                ({{ $subscription->is_active ? $subscription->days_remaining . ' days left' : 'ended' }}).
                This record is kept as it is; the renewal is recorded as a new term.
            </div>

            <form action="{{ route('subscriptions.renew', $subscription) }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="plan_key">Package <span class="text-danger">*</span></label>
                        <select class="form-control" id="plan_key" name="plan_key" required>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->key }}"
                                        data-months="{{ $plan->months }}"
                                        {{ old('plan_key', $subscription->plan_key) === $plan->key ? 'selected' : '' }}>
                                    {{ $plan->summary }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">
                            They can renew onto a different package — a bigger one if they need more farms.
                        </small>
                    </div>

                    {{-- Both dates are typed, not derived. A term sold on paper
                         rarely runs exactly to the arithmetic, and the created
                         date is not the date the farmer paid for. --}}
                    <div class="col-md-3 form-group">
                        <label for="starts_at">From <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="starts_at" name="starts_at" required
                               value="{{ old('starts_at', $startsAt) }}">
                        <small class="text-muted">Prefilled to the day after the current term ends.</small>
                    </div>

                    <div class="col-md-3 form-group">
                        <label for="expires_at">To <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="expires_at" name="expires_at" required
                               value="{{ old('expires_at', $expiresAt) }}">
                        <small class="text-muted">Prefilled from the package's months. Change it freely.</small>
                    </div>

                    <div class="col-12 form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"
                                  placeholder="Receipt number, who took the payment…">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check mr-1"></i> Record renewal
                </button>
                <a href="{{ route('subscriptions.show', $subscription) }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Changing the package re-derives the To date from From, so the admin is
    // not left with last package's length on a longer one. Either date can
    // still be typed over afterwards — this only moves it when the package
    // changes, never while they are editing the dates themselves.
    (function () {
        var plan  = document.getElementById('plan_key');
        var from  = document.getElementById('starts_at');
        var to    = document.getElementById('expires_at');
        if (!plan || !from || !to) return;

        function reprice() {
            var months = parseInt(plan.selectedOptions[0] && plan.selectedOptions[0].dataset.months, 10);
            if (!from.value || isNaN(months)) return;

            var d = new Date(from.value + 'T00:00:00');
            d.setMonth(d.getMonth() + months);
            d.setDate(d.getDate() - 1);

            to.value = d.getFullYear() + '-'
                + String(d.getMonth() + 1).padStart(2, '0') + '-'
                + String(d.getDate()).padStart(2, '0');
        }

        plan.addEventListener('change', reprice);
        from.addEventListener('change', reprice);
    })();
</script>
@endpush
