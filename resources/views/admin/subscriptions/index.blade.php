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
        <div class="row mb-3">
            <div class="col-md-4">
                <a href="{{ route('subscriptions.index', ['state' => 'active']) }}" class="text-decoration-none">
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
                <a href="{{ route('subscriptions.index', ['state' => 'expiring']) }}" class="text-decoration-none">
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
                <a href="{{ route('subscriptions.index', ['state' => 'expired']) }}" class="text-decoration-none">
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
                        Subscriptions <span class="badge bg-primary ml-2">{{ $subscriptions->total() }}</span>
                    </h4>
                    @permission('subscriptions.create')
                        <a href="{{ route('subscriptions.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus-circle mr-1"></i> Record Subscription
                        </a>
                    @endpermission
                </div>

                <form method="GET" class="form-inline mb-3">
                    <label class="mr-2 mb-0">Show</label>
                    <select name="state" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>Everything</option>
                        <option value="active" {{ $filter === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="expiring" {{ $filter === 'expiring' ? 'selected' : '' }}>Expiring soon</option>
                        <option value="expired" {{ $filter === 'expired' ? 'selected' : '' }}>Expired</option>
                        <option value="cancelled" {{ $filter === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                    <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm mr-2"
                           placeholder="Name or mobile">
                    <button class="btn btn-sm btn-outline-primary mr-2" type="submit">Search</button>
                    @if ($filter !== 'all' || $search !== '')
                        <a href="{{ route('subscriptions.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Farmer</th>
                                <th>Mobile</th>
                                <th>Package</th>
                                <th>Amount</th>
                                <th>Starts</th>
                                <th>Ends</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($subscriptions as $subscription)
                                {{-- Red for expired, amber for close to it. The class
                                     comes from the model so the list, the API and the
                                     counts above can never disagree about a row. --}}
                                <tr class="{{ $subscription->row_class }}">
                                    <td>
                                        {{ trim(($subscription->farmer->first_name ?? '') . ' ' . ($subscription->farmer->last_name ?? '')) ?: 'Farmer #' . $subscription->farmer_id }}
                                    </td>
                                    <td>{{ $subscription->farmer->mobile ?? '—' }}</td>
                                    <td>{{ $subscription->plan_label }}</td>
                                    <td>{{ config('subscriptions.currency_symbol', '₹') }}{{ number_format($subscription->amount, 0) }}</td>
                                    <td>{{ $subscription->starts_at?->format('d M Y') ?? '—' }}</td>
                                    <td>{{ $subscription->expires_at?->format('d M Y') ?? '—' }}</td>
                                    <td>
                                        @switch($subscription->state)
                                            @case('expired')
                                                <span class="badge badge-danger">Expired</span>
                                                @break
                                            @case('expiring')
                                                <span class="badge badge-warning">
                                                    {{ $subscription->days_remaining === 0
                                                        ? 'Ends today'
                                                        : $subscription->days_remaining . ' day' . ($subscription->days_remaining === 1 ? '' : 's') . ' left' }}
                                                </span>
                                                @break
                                            @case('cancelled')
                                                <span class="badge badge-secondary">Cancelled</span>
                                                @break
                                            @default
                                                <span class="badge badge-success">
                                                    Active · {{ $subscription->days_remaining }} days left
                                                </span>
                                        @endswitch
                                    </td>
                                    <td class="text-right text-nowrap">
                                        <a href="{{ route('subscriptions.show', $subscription) }}"
                                           class="btn btn-sm btn-outline-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @permission('subscriptions.update')
                                            <a href="{{ route('subscriptions.edit', $subscription) }}"
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @unless ($subscription->is_cancelled)
                                                <form method="POST" action="{{ route('subscriptions.cancel', $subscription) }}"
                                                      class="d-inline js-confirm"
                                                      data-message="Cancel this subscription? The farmer will not be able to add more farms.">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Cancel">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                        @endpermission
                                        @permission('subscriptions.delete')
                                            <form method="POST" action="{{ route('subscriptions.destroy', $subscription) }}"
                                                  class="d-inline js-confirm"
                                                  data-message="Delete this record permanently? Cancel it instead if the farmer really did subscribe.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endpermission
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        No subscriptions
                                        @if ($filter !== 'all' || $search !== '')
                                            match this filter.
                                        @else
                                            recorded yet.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Bootstrap 4 explicitly. Laravel 12 defaults the paginator
                     to Tailwind, which renders as unstyled stacked links in
                     this panel. Named here rather than switched globally in a
                     service provider, because this is the only paginated view
                     in the admin and a global change would be a surprise
                     waiting for whoever adds the next one. --}}
                {{ $subscriptions->links('pagination::bootstrap-4') }}
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('form.js-confirm').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    Swal.fire({
                        title: 'Are you sure?',
                        text: form.dataset.message || '',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        confirmButtonText: 'Yes, continue'
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
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
