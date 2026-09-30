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
            <p class="text-muted small">
                A package grants a number of farms for a number of months. What a farmer may own is
                the free allowance ({{ config('subscriptions.free_farm_limit', 2) }})
                plus every package they currently hold — so a farmer needing one more farm buys
                another package alongside the one they have.
            </p>

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
                                              method="POST" class="d-inline">
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
@endsection
