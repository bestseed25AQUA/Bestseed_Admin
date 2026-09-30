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
                                @php
                                    $farmerName = trim(($subscription->farmer->first_name ?? '') . ' ' . ($subscription->farmer->last_name ?? ''))
                                        ?: 'Farmer #' . $subscription->farmer_id;
                                @endphp
                                <tr class="{{ $subscription->row_class }}">
                                    <td>{{ $farmerName }}</td>
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
                                            {{-- Renew records a NEW term; Edit corrects
                                                 the existing one. Two different things, so
                                                 two buttons — correcting a typo must not
                                                 look like taking another payment. --}}
                                            <a href="{{ route('subscriptions.renew.form', $subscription) }}"
                                               class="btn btn-sm btn-outline-success" title="Renew">
                                                <i class="fas fa-redo"></i>
                                            </a>
                                            <a href="{{ route('subscriptions.edit', $subscription) }}"
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @unless ($subscription->is_cancelled)
                                                <form method="POST" action="{{ route('subscriptions.cancel', $subscription) }}"
                                                      class="d-inline js-confirm"
                                                      data-title="Cancel this subscription?"
                                                      data-message="{{ $farmerName }} loses the {{ $subscription->plan_label }} package straight away, {{ $subscription->days_remaining }} day(s) before it was due to end on {{ $subscription->expires_at?->format('d M Y') }}. Their farm allowance drops by {{ $subscription->farm_limit }}. Farms they already created stay. Reversing this means recording a new subscription."
                                                      data-confirm-text="Yes, cancel it">
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
                                                  data-title="Delete this record permanently?"
                                                  data-message="{{ $farmerName }}'s {{ $subscription->plan_label }} record ({{ $subscription->farm_limit }} farms, {{ config('subscriptions.currency_symbol', '₹') }}{{ number_format($subscription->amount, 0) }}) is erased, along with any reminders sent for it. Their allowance drops by {{ $subscription->farm_limit }}. There is no undo — cancel it instead if the farmer really did pay."
                                                  data-confirm-text="Yes, delete permanently">
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
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <small class="text-muted">
                        @if ($subscriptions->total() > 0)
                            Showing {{ $subscriptions->firstItem() }}–{{ $subscriptions->lastItem() }}
                            of {{ $subscriptions->total() }}
                        @else
                            Nothing to show
                        @endif
                    </small>
                    <div class="ml-auto">
                        {{ $subscriptions->links('pagination::bootstrap-4') }}
                    </div>
                </div>

<span id="subTotal" class="d-none" data-total="{{ $subscriptions->total() }}"></span>
