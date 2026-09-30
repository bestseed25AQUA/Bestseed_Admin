@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title d-flex align-items-center">
            <i class="fas fa-box-open mr-2"></i> Subscription Packages
        </h3>
        <a href="{{ route('subscription-plans.create') }}" class="btn btn-sm btn-primary float-right">
            <i class="fas fa-plus mr-1"></i> New Package
        </a>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('subscriptions.index') }}">Subscriptions</a></li>
                <li class="breadcrumb-item active" aria-current="page">Packages</li>
            </ol>
        </nav>
    </div>

    @include('admin.partials.flash')

    <div class="card">
        <div class="card-body">

            @if ($plans->isEmpty())
                <p class="text-muted mb-0">No packages yet. Create one to start selling.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Package</th><th>Months</th><th>Farms</th><th>Price</th>
                                <th>Warns from</th><th>Sold</th><th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plans as $plan)
                                <tr class="{{ $plan->is_active ? '' : 'text-muted' }}">
                                    <td>
                                        <strong>{{ $plan->label }}</strong>
                                        <div class="small text-muted">{{ $plan->key }}</div>
                                    </td>
                                    <td>{{ $plan->months }}</td>
                                    <td><span class="badge bg-info">{{ $plan->farm_limit }}</span></td>
                                    <td>{{ config('subscriptions.currency_symbol', '₹') }}{{ number_format((float) $plan->amount, 2) }}</td>
                                    <td>{{ $plan->warnFromDays() }} days</td>
                                    <td>
                                        {{ $plan->subscriptions_count }}
                                        <span class="small text-muted">({{ $plan->active_subscriptions_count }} live)</span>
                                    </td>
                                    <td>
                                        @if ($plan->is_active)
                                            <span class="badge bg-success">On sale</span>
                                        @else
                                            <span class="badge bg-secondary">Retired</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <a class="btn btn-sm btn-primary btn-action"
                                           href="{{ route('subscription-plans.edit', $plan) }}" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        {{-- Retired, never deleted: subscriptions already sold point
                                             at it and their history must keep reading correctly. --}}
                                        <form action="{{ route('subscription-plans.toggle', $plan) }}"
                                              method="POST" class="d-inline js-confirm"
                                              data-title="{{ $plan->is_active ? 'Retire this package?' : 'Put this package back on sale?' }}"
                                              data-message="{{ $plan->is_active
                                                  ? '"' . $plan->label . '" stops being offered to farmers and disappears from the Add Subscription screen. The ' . $plan->active_subscriptions_count . ' live subscription(s) already sold on it keep running to their end dates and are not affected.'
                                                  : '"' . $plan->label . '" starts being offered again — ' . $plan->farm_limit . ' farm(s) for ' . $plan->months . ' month(s) at ' . config('subscriptions.currency_symbol', '₹') . number_format($plan->amount, 0) . '.' }}"
                                              data-confirm-text="{{ $plan->is_active ? 'Yes, retire it' : 'Yes, put on sale' }}">
                                            @csrf
                                            <button class="btn btn-sm {{ $plan->is_active ? 'btn-outline-secondary' : 'btn-success' }} btn-action"
                                                    title="{{ $plan->is_active ? 'Retire' : 'Put back on sale' }}">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form.js-confirm').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                Swal.fire({
                    title: form.dataset.title || 'Are you sure?',
                    text: form.dataset.message || '',
                    icon: 'warning',
                    width: 560,
                    showCancelButton: true,
                    confirmButtonText: form.dataset.confirmText || 'Yes, continue',
                    cancelButtonText: 'Go back'
                }).then(function (result) {
                    if (result.isConfirmed) form.submit();
                });
            });
        });

        @if (session('success'))
            Swal.fire({
                toast: true, position: 'top-right', icon: 'success',
                title: "{{ addslashes(session('success')) }}",
                showConfirmButton: false, timer: 3500, timerProgressBar: true
            });
        @endif

        @if (session('error'))
            Swal.fire({
                toast: true, position: 'top-right', icon: 'error',
                title: "{{ addslashes(session('error')) }}",
                showConfirmButton: false, timer: 3500, timerProgressBar: true
            });
        @endif
    });
</script>
@endsection
