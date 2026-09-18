@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-id-card mr-2"></i>Subscription
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('subscriptions.index') }}">Subscriptions</a></li>
                    <li class="breadcrumb-item active" aria-current="page">#{{ $subscription->id }}</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h4 class="mb-1">
                                    {{ trim(($subscription->farmer->first_name ?? '') . ' ' . ($subscription->farmer->last_name ?? ''))
                                        ?: 'Farmer #' . $subscription->farmer_id }}
                                </h4>
                                <div class="text-muted">{{ $subscription->farmer->mobile ?? '—' }} · {{ $farms }} farm(s)</div>
                            </div>
                            @switch($subscription->state)
                                @case('expired')
                                    <span class="badge badge-danger p-2">Expired</span>
                                    @break
                                @case('expiring')
                                    <span class="badge badge-warning p-2">
                                        {{ $subscription->days_remaining === 0
                                            ? 'Ends today'
                                            : $subscription->days_remaining . ' day' . ($subscription->days_remaining === 1 ? '' : 's') . ' left' }}
                                    </span>
                                    @break
                                @case('cancelled')
                                    <span class="badge badge-secondary p-2">Cancelled</span>
                                    @break
                                @default
                                    <span class="badge badge-success p-2">Active</span>
                            @endswitch
                        </div>

                        <table class="table table-sm mb-0">
                            <tr>
                                <th style="width: 40%;">Package</th>
                                <td>{{ $subscription->plan_label }}</td>
                            </tr>
                            <tr>
                                <th>Amount paid</th>
                                <td>{{ config('subscriptions.currency_symbol', '₹') }}{{ number_format($subscription->amount, 2) }}</td>
                            </tr>
                            <tr>
                                <th>Duration</th>
                                <td>{{ $subscription->months }} month{{ $subscription->months === 1 ? '' : 's' }}</td>
                            </tr>
                            <tr>
                                <th>Starts</th>
                                <td>{{ $subscription->starts_at?->format('d M Y') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Ends</th>
                                <td>{{ $subscription->expires_at?->format('d M Y') ?? '—' }}</td>
                            </tr>
                            @if ($subscription->cancelled_at)
                                <tr>
                                    <th>Cancelled</th>
                                    <td>{{ $subscription->cancelled_at->format('d M Y, H:i') }}</td>
                                </tr>
                            @endif
                            <tr>
                                <th>Recorded</th>
                                <td>{{ $subscription->created_at?->format('d M Y, H:i') ?? '—' }}</td>
                            </tr>
                            @if ($subscription->notes)
                                <tr>
                                    <th>Notes</th>
                                    <td>{{ $subscription->notes }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Reminders sent</h5>
                        @if ($subscription->reminders->isEmpty())
                            <p class="text-muted mb-0">None yet.</p>
                        @else
                            <ul class="list-unstyled mb-0">
                                @foreach ($subscription->reminders->sortByDesc('days_before') as $reminder)
                                    <li class="d-flex justify-content-between border-bottom py-2">
                                        <span>
                                            {{ $reminder->days_before === 0
                                                ? 'On expiry day'
                                                : $reminder->days_before . ' day' . ($reminder->days_before === 1 ? '' : 's') . ' before' }}
                                        </span>
                                        <span class="text-muted">{{ $reminder->sent_at?->format('d M Y') ?? '—' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="mt-3">
                    @permission('subscriptions.update')
                        <a href="{{ route('subscriptions.edit', $subscription) }}" class="btn btn-primary">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </a>
                    @endpermission
                    <a href="{{ route('subscriptions.index') }}" class="btn btn-light">Back</a>
                </div>
            </div>
        </div>
    </div>
@endsection
