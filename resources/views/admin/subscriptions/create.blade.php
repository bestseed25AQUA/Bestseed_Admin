@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-id-card mr-2"></i>Record a Subscription
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('subscriptions.index') }}">Subscriptions</a></li>
                    <li class="breadcrumb-item active" aria-current="page">New</li>
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

                        {{-- Step 1: find the caller.
                             By mobile number and nothing else. A name search
                             invites picking the wrong "Ramesh" out of a list and
                             giving a stranger's account a subscription somebody
                             else paid for. --}}
                        <h5 class="mb-3">1. Find the farmer</h5>
                        <div class="form-group">
                            <label>Mobile number</label>
                            <div class="input-group">
                                <input type="text" id="lookupMobile" class="form-control"
                                       placeholder="10-digit mobile" maxlength="15"
                                       autocomplete="off">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-primary" type="button" id="lookupBtn">
                                        <i class="fas fa-search mr-1"></i> Find
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">
                                The farmer must already have an account in the app.
                            </small>
                        </div>

                        <div id="lookupResult" class="mb-4"></div>

                        <hr>

                        <form method="POST" action="{{ route('subscriptions.store') }}" id="subscriptionForm">
                            @csrf
                            <input type="hidden" name="farmer_id" id="farmerId"
                                   value="{{ old('farmer_id', $farmer->id ?? '') }}">

                            <h5 class="mb-3">2. Pick the package they paid for</h5>

                            <div class="row">
                                @foreach ($plans as $key => $plan)
                                    <div class="col-md-6 mb-3">
                                        <label class="w-100 mb-0" style="cursor: pointer;">
                                            <input type="radio" name="plan_key" value="{{ $key }}"
                                                   class="plan-radio"
                                                   {{ old('plan_key') === $key ? 'checked' : '' }}
                                                   style="position:absolute; opacity:0;">
                                            <div class="card plan-card h-100">
                                                <div class="card-body d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">{{ $plan['label'] }}</h6>
                                                        <small class="text-muted">
                                                            {{ $plan['months'] }} month{{ $plan['months'] === 1 ? '' : 's' }}
                                                        </small>
                                                    </div>
                                                    <h4 class="mb-0 text-primary">
                                                        {{ $currency }}{{ number_format($plan['amount'], 0) }}
                                                    </h4>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <h5 class="mb-3 mt-4">3. Details</h5>

                            <div class="form-group">
                                <label>Start date <span class="text-muted">(optional)</span></label>
                                <input type="date" name="starts_at" class="form-control"
                                       value="{{ old('starts_at') }}">
                                <small class="form-text text-muted">
                                    Leave blank to start today. If the farmer already has a live
                                    subscription, the new one starts the day after that one ends
                                    so no paid time is lost.
                                </small>
                            </div>

                            <div class="form-group">
                                <label>Notes <span class="text-muted">(optional)</span></label>
                                <textarea name="notes" rows="3" class="form-control"
                                          placeholder="Payment reference, who took the call, anything worth remembering">{{ old('notes') }}</textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" id="saveBtn">
                                <i class="fas fa-check mr-1"></i> Save Subscription
                            </button>
                            <a href="{{ route('subscriptions.index') }}" class="btn btn-light">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">How this works</h5>
                        <p class="text-muted mb-2">
                            A farmer may create {{ config('subscriptions.free_farm_limit', 2) }} farms for free.
                            To add more they need a live subscription.
                        </p>
                        <p class="text-muted mb-2">
                            They pick a package in the app, call the Farm Management helpline
                            and pay. You record it here. There is no payment inside the app.
                        </p>
                        <p class="text-muted mb-0">
                            Saving takes effect immediately — the farmer can add farms as soon
                            as this page is submitted.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .plan-card { border: 2px solid #e9ecef; transition: border-color .15s ease-in-out; }
        .plan-radio:checked + .plan-card { border-color: #0d6efd; background-color: #f4f8ff; }
        .plan-radio:focus + .plan-card { box-shadow: 0 0 0 .2rem rgba(13,110,253,.25); }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var mobileInput = document.getElementById('lookupMobile');
            var lookupBtn   = document.getElementById('lookupBtn');
            var resultBox   = document.getElementById('lookupResult');
            var farmerId    = document.getElementById('farmerId');

            function escapeHtml(value) {
                var div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            }

            function render(html) {
                resultBox.innerHTML = html;
            }

            function lookup() {
                var mobile = mobileInput.value.replace(/\D/g, '');

                if (mobile.length < 10) {
                    render('<div class="alert alert-warning mb-0">Enter the full 10-digit mobile number.</div>');
                    return;
                }

                lookupBtn.disabled = true;
                render('<div class="text-muted">Searching…</div>');

                fetch('{{ route('subscriptions.lookup') }}?mobile=' + encodeURIComponent(mobile), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (response) {
                        return response.json().then(function (body) {
                            return { ok: response.ok, body: body };
                        });
                    })
                    .then(function (result) {
                        lookupBtn.disabled = false;

                        if (!result.ok || !result.body.status) {
                            farmerId.value = '';
                            render('<div class="alert alert-danger mb-0">'
                                + escapeHtml(result.body.message || 'Farmer not found.')
                                + '</div>');
                            return;
                        }

                        var farmer = result.body.farmer;
                        var active = result.body.active;
                        farmerId.value = farmer.id;

                        // Showing the existing term matters: it is what stops
                        // the person on the phone charging twice for days the
                        // farmer already has.
                        var existing = active
                            ? '<div class="alert alert-info mb-0 mt-2">Already subscribed: <strong>'
                                + escapeHtml(active.plan_label) + '</strong> until <strong>'
                                + escapeHtml(active.expires_on) + '</strong> ('
                                + escapeHtml(active.days_remaining) + ' days left).'
                                + ' A new package will start the day after that.</div>'
                            : '<div class="alert alert-secondary mb-0 mt-2">No active subscription.</div>';

                        render('<div class="alert alert-success mb-0"><strong>'
                            + escapeHtml(farmer.name) + '</strong> · ' + escapeHtml(farmer.mobile)
                            + ' · ' + escapeHtml(farmer.farms) + ' farm(s)</div>' + existing);
                    })
                    .catch(function () {
                        lookupBtn.disabled = false;
                        farmerId.value = '';
                        render('<div class="alert alert-danger mb-0">Could not reach the server. Try again.</div>');
                    });
            }

            lookupBtn.addEventListener('click', lookup);

            mobileInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    // Otherwise Enter submits the subscription form with no
                    // farmer chosen.
                    event.preventDefault();
                    lookup();
                }
            });

            document.getElementById('subscriptionForm').addEventListener('submit', function (event) {
                if (!farmerId.value) {
                    event.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'No farmer selected',
                        text: 'Find the farmer by mobile number before saving.'
                    });
                }
            });
        });
    </script>
@endsection
