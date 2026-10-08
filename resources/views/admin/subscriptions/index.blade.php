@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-id-card mr-2"></i>Farm Management Subscriptions
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Subscriptions</li>
                </ol>
            </nav>
        </div>

        {{-- The admin half of "notify us too". These are computed from the
             dates on every page load, so they stay correct even if the nightly
             reminder command has not run. --}}
        {{-- Renewals due in the next {{ $noticeDays }} days.

             Shown above everything, on every filter, because it is the one
             thing on this screen that needs doing TODAY. Deliberately a flat
             horizon rather than each package's own warning window: a one-month
             package warns at 7 days, so a monthly customer would never appear
             in a "next 15 days" list if this followed the package. --}}
        @if ($dueSoon->isNotEmpty())
            <div class="alert alert-warning">
                <h5 class="mb-2">
                    <i class="fas fa-bell mr-1"></i>
                    {{ $dueSoon->count() }} {{ Str::plural('farm', $dueSoon->count()) }}
                    expiring within {{ $noticeDays }} days
                </h5>

                <div class="table-responsive">
                    <table class="table table-sm mb-0 bg-white">
                        <thead>
                            <tr>
                                <th>Farmer</th><th>Package</th><th>Farm</th>
                                <th>Expires</th><th>Days left</th><th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dueSoon->take($dueSoonCap) as $due)
                                <tr>
                                    <td>
                                        {{ trim(($due->farmer->first_name ?? '') . ' ' . ($due->farmer->last_name ?? '')) ?: 'Farmer #' . $due->farmer_id }}
                                        <div class="small text-muted">{{ $due->farmer->mobile ?? '' }}</div>
                                    </td>
                                    <td>
                                        @if ($due->is_free)
                                            <span class="badge badge-info">Free trial</span>
                                        @else
                                            {{ $due->label }}
                                        @endif
                                    </td>
                                    <td>{{ $due->farms }}</td>
                                    <td>{{ $due->expires_at->format('d M Y') }}</td>
                                    <td>
                                        {{-- 0 is today, not "expired": the term
                                             runs to the end of its last day. --}}
                                        <span class="badge {{ $due->days <= 3 ? 'bg-danger text-white' : 'bg-warning text-dark' }}">
                                            {{ $due->days }}
                                            {{ Str::plural('day', $due->days) }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ $due->renew_url }}" class="btn btn-sm btn-primary">
                                            <i class="fas {{ $due->is_free ? 'fa-plus' : 'fa-redo' }} mr-1"></i>
                                            {{ $due->is_free ? 'Sell' : 'Renew' }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($dueSoon->count() > $dueSoonCap)
                    <small class="text-muted d-block mt-2">
                        Showing the first {{ $dueSoonCap }}, soonest first.
                    </small>
                @endif
            </div>
        @endif

        <div class="row mb-3">
            <div class="col-md-4">
                <a href="{{ route('subscriptions.index', ['state' => 'active']) }}" class="stat-card-link">
                    <div class="card border-left-success">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Active</span>
                                <h3 class="mb-0 text-success">{{ $counts['active'] }}</h3>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('subscriptions.index', ['state' => 'expiring']) }}" class="stat-card-link">
                    <div class="card {{ $counts['expiring'] > 0 ? 'bg-warning text-dark' : '' }}">
                        <div class="card-body py-3">
                            {{-- Not "within N days": the window follows the
                                 plan, so a monthly one counts here from 7 days
                                 out and an annual one from 30. --}}
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Expiring soon</span>
                                <h3 class="mb-0">{{ $counts['expiring'] }}</h3>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('subscriptions.index', ['state' => 'expired']) }}" class="stat-card-link">
                    <div class="card {{ $counts['expired'] > 0 ? 'bg-danger text-white' : '' }}">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Expired</span>
                                <h3 class="mb-0">{{ $counts['expired'] }}</h3>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">
                        Subscriptions <span class="badge bg-primary text-white ml-2" id="subCount">{{ $subscriptions->total() + count($freeTrials ?? []) }}</span>
                    </h4>
                    @permission('subscriptions.create')
                        <a href="{{ route('subscriptions.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus-circle mr-1"></i> Record Subscription
                        </a>
                    @endpermission
                </div>

                <form method="GET" class="form-inline mb-3" id="subFilters" data-url="{{ route('subscriptions.index') }}">
                    <label class="mr-2 mb-0">Show</label>
                    <select name="state" class="form-control form-control-sm mr-2">
                        <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>Everything</option>
                        <option value="active" {{ $filter === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="expiring" {{ $filter === 'expiring' ? 'selected' : '' }}>Expiring soon</option>
                        <option value="expired" {{ $filter === 'expired' ? 'selected' : '' }}>Expired</option>
                        <option value="cancelled" {{ $filter === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        <option value="replaced" {{ $filter === 'replaced' ? 'selected' : '' }}>Replaced by a renewal</option>
                    </select>
                    <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm mr-2"
                           placeholder="Name or mobile">
                    <button class="btn btn-sm btn-outline-primary mr-2" type="submit">Search</button>
                    <label class="mr-2 mb-0">Per page</label>
                    <select name="per_page" class="form-control form-control-sm mr-2">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" {{ $perPage === $option ? 'selected' : '' }}>{{ $option }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('subscriptions.index') }}" id="subClear"
                       class="btn btn-sm btn-outline-secondary {{ $filter === 'all' && $search === '' && $perPage === 25 ? 'd-none' : '' }}">Clear</a>
                </form>

                <div id="subList">
                    @include('admin.subscriptions.partials.list')
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Delegated, because the rows are replaced as the admin types.
            document.addEventListener('submit', function (event) {
                var form = event.target.closest('form.js-confirm');
                if (!form) return;

                event.preventDefault();
                Swal.fire({
                    title: form.dataset.title || 'Are you sure?',
                    text: form.dataset.message || '',
                    icon: 'warning',
                    width: 560,
                    showCancelButton: true,
                    reverseButtons: true,
                    buttonsStyling: false,
                    customClass: {
                        popup: 'swal-tidy',
                        title: 'swal-tidy-title',
                        confirmButton: 'btn btn-danger px-4',
                        cancelButton: 'btn btn-light border px-4'
                    },
                    confirmButtonText: form.dataset.confirmText || 'Yes, continue',
                    cancelButtonText: 'Go back'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

            var filters = document.getElementById('subFilters');
            var list    = document.getElementById('subList');
            var count   = document.getElementById('subCount');
            var timer   = null;
            var request = 0;

            function load(url) {
                var ticket = ++request;

                list.style.opacity = '.5';

                fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'partial=1', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (response) { return response.text(); })
                    .then(function (html) {
                        // A slow earlier keystroke must not overwrite a newer result.
                        if (ticket !== request) return;

                        list.innerHTML = html;
                        list.style.opacity = '';

                        var total = list.querySelector('#subTotal');
                        if (total && count) count.textContent = total.dataset.total;

                        history.replaceState(null, '', url);
                    })
                    .catch(function () {
                        if (ticket === request) list.style.opacity = '';
                    });
            }

            var clear = document.getElementById('subClear');

            function currentUrl() {
                var params = new URLSearchParams(new FormData(filters));

                clear.classList.toggle('d-none',
                    params.get('state') === 'all'
                    && params.get('q') === ''
                    && params.get('per_page') === '25');

                return filters.dataset.url + '?' + params.toString();
            }

            filters.addEventListener('submit', function (event) {
                event.preventDefault();
                clearTimeout(timer);
                load(currentUrl());
            });

            filters.querySelector('input[name="q"]').addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { load(currentUrl()); }, 300);
            });

            filters.querySelectorAll('select').forEach(function (select) {
                select.addEventListener('change', function () {
                    clearTimeout(timer);
                    load(currentUrl());
                });
            });

            // Paging stays on the page too, so the search box keeps its text.
            list.addEventListener('click', function (event) {
                var link = event.target.closest('.pagination a');
                if (!link || !link.href) return;
                event.preventDefault();
                load(link.href);
            });

            @if (session('success'))
                Swal.fire({
                    toast: true,
                    position: 'top-right',
                    icon: 'success',
                    title: "{{ addslashes(session('success')) }}",
                    showConfirmButton: false,
                    timer: 3500,
                    timerProgressBar: true
                });
            @endif

            @if (session('error'))
                Swal.fire({
                    toast: true,
                    position: 'top-right',
                    icon: 'error',
                    title: "{{ addslashes(session('error')) }}",
                    showConfirmButton: false,
                    timer: 3500,
                    timerProgressBar: true
                });
            @endif
        });
    </script>
@endsection
