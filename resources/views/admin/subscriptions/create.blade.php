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
                        <div class="form-group position-relative">
                            <label>Mobile number or name</label>
                            <input type="text" id="lookupMobile" class="form-control"
                                   placeholder="Start typing a mobile number or name"
                                   maxlength="40" autocomplete="off">
                            <div id="lookupSuggestions" class="farmer-suggestions d-none"></div>
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
                                {{-- Models from the catalogue now, not a config
                                     array — so `$plan->label`, never
                                     `$plan['label']`. --}}
                                @foreach ($plans as $plan)
                                    <div class="col-md-6 mb-3">
                                        <label class="w-100 mb-0" style="cursor: pointer;">
                                            <input type="radio" name="plan_key" value="{{ $plan->key }}"
                                                   class="plan-radio"
                                                   data-months="{{ $plan->months }}"
                                                   {{ old('plan_key') === $plan->key ? 'checked' : '' }}
                                                   style="position:absolute; opacity:0;">
                                            <div class="card plan-card h-100">
                                                <div class="card-body d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">{{ $plan->label }}</h6>
                                                        <small class="text-muted d-block">
                                                            {{ $plan->months }} month{{ $plan->months === 1 ? '' : 's' }}
                                                        </small>
                                                        {{-- The number the farmer is buying. --}}
                                                        <span class="badge bg-info">
                                                            {{ $plan->farm_limit }}
                                                            {{ Str::plural('farm', $plan->farm_limit) }}
                                                        </span>
                                                    </div>
                                                    <h4 class="mb-0 text-primary">
                                                        {{ $currency }}{{ number_format((float) $plan->amount, 0) }}
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
            var input       = document.getElementById('lookupMobile');
            var suggestions = document.getElementById('lookupSuggestions');
            var resultBox   = document.getElementById('lookupResult');
            var farmerId    = document.getElementById('farmerId');

            var timer   = null;
            var matches = [];
            var active  = -1;

            function escapeHtml(value) {
                var div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            }

            function hide() {
                suggestions.classList.add('d-none');
                suggestions.innerHTML = '';
                active = -1;
            }

            function render() {
                if (matches.length === 0) {
                    suggestions.innerHTML =
                        '<div class="farmer-suggestion-empty">No farmer found. '
                        + 'They must have an account in the app first.</div>';
                    suggestions.classList.remove('d-none');
                    return;
                }

                suggestions.innerHTML = matches.map(function (f, i) {
                    var held = f.active
                        ? '<span class="badge badge-success">' + escapeHtml(f.active.plan_label)
                          + ' to ' + escapeHtml(f.active.expires_on) + '</span>'
                        : '<span class="badge badge-light border">No subscription</span>';

                    return '<button type="button" class="farmer-suggestion' + (i === active ? ' is-active' : '') + '" data-index="' + i + '">'
                        + '<span class="farmer-suggestion-main">'
                        + '<strong>' + escapeHtml(f.name) + '</strong>'
                        + '<span class="text-muted"> · ' + escapeHtml(f.mobile) + '</span>'
                        + '</span>'
                        + '<span class="farmer-suggestion-meta">'
                        + escapeHtml(f.farms) + ' farm(s) ' + held
                        + '</span>'
                        + '</button>';
                }).join('');

                suggestions.classList.remove('d-none');
            }

            function choose(index) {
                var farmer = matches[index];
                if (!farmer) return;

                farmerId.value = farmer.id;
                input.value = farmer.mobile;
                hide();

                var held = farmer.active
                    ? '<div class="alert alert-info mb-0 mt-2">Already subscribed: <strong>'
                        + escapeHtml(farmer.active.plan_label) + '</strong> until <strong>'
                        + escapeHtml(farmer.active.expires_on) + '</strong> ('
                        + escapeHtml(farmer.active.days_remaining) + ' days left).'
                        + ' A new package is added on top of what they already hold.</div>'
                    : '<div class="alert alert-secondary mb-0 mt-2">No active subscription.</div>';

                resultBox.innerHTML = '<div class="alert alert-success mb-0"><strong>'
                    + escapeHtml(farmer.name) + '</strong> · ' + escapeHtml(farmer.mobile)
                    + ' · ' + escapeHtml(farmer.farms) + ' farm(s)</div>' + held;
            }

            function search() {
                var term = input.value.trim();

                farmerId.value = '';
                resultBox.innerHTML = '';

                if (term.length < 3) {
                    hide();
                    return;
                }

                fetch('{{ route('subscriptions.lookup') }}?q=' + encodeURIComponent(term), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (response) { return response.json(); })
                    .then(function (body) {
                        matches = body.farmers || [];
                        active = -1;
                        render();
                    })
                    .catch(function () { hide(); });
            }

            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(search, 250);
            });

            input.addEventListener('focus', function () {
                if (matches.length > 0 && input.value.trim().length >= 3) render();
            });

            input.addEventListener('keydown', function (event) {
                if (suggestions.classList.contains('d-none')) {
                    if (event.key === 'Enter') event.preventDefault();
                    return;
                }

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    active = Math.min(active + 1, matches.length - 1);
                    render();
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    active = Math.max(active - 1, 0);
                    render();
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    choose(active >= 0 ? active : 0);
                } else if (event.key === 'Escape') {
                    hide();
                }
            });

            suggestions.addEventListener('mousedown', function (event) {
                var button = event.target.closest('.farmer-suggestion');
                if (!button) return;
                event.preventDefault();
                choose(parseInt(button.dataset.index, 10));
            });

            document.addEventListener('click', function (event) {
                if (!suggestions.contains(event.target) && event.target !== input) hide();
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
