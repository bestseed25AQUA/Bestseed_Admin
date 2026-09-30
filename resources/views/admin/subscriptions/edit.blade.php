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

                        <form method="POST" action="{{ route('subscriptions.update', $subscription) }}" id="editForm">
                            @csrf
                            @method('PUT')

                            <div class="form-group">
                                <label>Package</label>
                                <select name="plan_key" id="planKey" class="form-control" required>
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->key }}"
                                            data-label="{{ $plan->label }}"
                                            data-months="{{ $plan->months }}"
                                            data-farms="{{ $plan->farm_limit }}"
                                            data-amount="{{ number_format($plan->amount, 2, '.', '') }}"
                                            {{ old('plan_key', $subscription->plan_key) === $plan->key ? 'selected' : '' }}>
                                            {{ $plan->summary }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    Changing the package recalculates the end date from the start
                                    date. Adjust it by hand afterwards if you need to.
                                </small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Starts on</label>
                                    <input type="date" name="starts_at" id="startsAt" class="form-control" required
                                           value="{{ old('starts_at', $subscription->starts_at?->toDateString()) }}">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Ends on</label>
                                    <input type="date" name="expires_at" id="expiresAt" class="form-control" required
                                           value="{{ old('expires_at', $subscription->expires_at?->toDateString()) }}">
                                    <small class="form-text text-muted">Inclusive — access lasts all of this day.</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" rows="3" class="form-control">{{ old('notes', $subscription->notes) }}</textarea>
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form     = document.getElementById('editForm');
            var planKey  = document.getElementById('planKey');
            var startsAt = document.getElementById('startsAt');
            var expiresAt = document.getElementById('expiresAt');

            var original = {
                key:     @json($subscription->plan_key),
                label:   @json($subscription->plan_label),
                months:  {{ (int) $subscription->months }},
                farms:   {{ (int) $subscription->farm_limit }},
                amount:  '{{ number_format($subscription->amount, 2, '.', '') }}',
                starts:  @json($subscription->starts_at?->toDateString()),
                expires: @json($subscription->expires_at?->toDateString())
            };

            var currency = @json(config('subscriptions.currency_symbol', '₹'));

            function selected() {
                return planKey.options[planKey.selectedIndex];
            }

            function formatDate(iso) {
                if (!iso) return '—';
                var parts = iso.split('-');
                var d = new Date(parts[0], parts[1] - 1, parts[2]);
                return d.toLocaleDateString('en-GB', {
                    day: '2-digit', month: 'short', year: 'numeric'
                });
            }

            // months from the start date, minus a day: a 2-month term starting
            // on the 1st runs to the last day of the second month.
            function endFor(startIso, months) {
                if (!startIso) return '';
                var parts = startIso.split('-');
                var d = new Date(parts[0], parts[1] - 1, parts[2]);
                var day = d.getDate();
                d.setMonth(d.getMonth() + months);
                if (d.getDate() !== day) d.setDate(0);
                d.setDate(d.getDate() - 1);
                var m = String(d.getMonth() + 1).padStart(2, '0');
                var dd = String(d.getDate()).padStart(2, '0');
                return d.getFullYear() + '-' + m + '-' + dd;
            }

            function recalculate() {
                var months = parseInt(selected().dataset.months, 10);
                var next = endFor(startsAt.value, months);
                if (next) expiresAt.value = next;
            }

            planKey.addEventListener('change', recalculate);
            startsAt.addEventListener('change', recalculate);

            function changeRows() {
                var opt = selected();
                var rows = [];

                if (opt.value !== original.key) {
                    rows.push(['Package', original.label, opt.dataset.label]);
                    rows.push(['Duration', original.months + ' month(s)', opt.dataset.months + ' month(s)']);
                }

                if (parseInt(opt.dataset.farms, 10) !== original.farms) {
                    rows.push(['Farms allowed', original.farms, opt.dataset.farms]);
                }

                if (opt.dataset.amount !== original.amount) {
                    rows.push(['Amount', currency + original.amount, currency + opt.dataset.amount]);
                }

                if (startsAt.value !== original.starts) {
                    rows.push(['Starts on', formatDate(original.starts), formatDate(startsAt.value)]);
                }

                if (expiresAt.value !== original.expires) {
                    rows.push(['Ends on', formatDate(original.expires), formatDate(expiresAt.value)]);
                }

                return rows;
            }

            function escapeHtml(value) {
                var div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            }

            function dialog(options) {
                options.buttonsStyling = false;
                options.reverseButtons = true;
                options.customClass = {
                    popup: 'swal-tidy',
                    title: 'swal-tidy-title',
                    confirmButton: 'btn btn-primary px-4',
                    cancelButton: 'btn btn-light border px-4'
                };
                return options;
            }

            var confirmed = false;

            form.addEventListener('submit', function (event) {
                if (confirmed) return;

                event.preventDefault();

                if (!startsAt.value || !expiresAt.value) {
                    Swal.fire(dialog({
                        icon: 'error',
                        title: 'Dates missing',
                        text: 'Both the start and the end date are needed.',
                        confirmButtonText: 'OK'
                    }));
                    return;
                }

                if (expiresAt.value < startsAt.value) {
                    Swal.fire(dialog({
                        icon: 'error',
                        title: 'End date is before the start date',
                        text: 'The term ends on ' + formatDate(expiresAt.value)
                            + ' but starts on ' + formatDate(startsAt.value) + '.',
                        confirmButtonText: 'OK'
                    }));
                    return;
                }

                var rows = changeRows();

                if (rows.length === 0) {
                    Swal.fire(dialog({
                        icon: 'info',
                        title: 'Nothing changed',
                        text: 'Edit something first, or press Cancel to go back.',
                        confirmButtonText: 'OK'
                    }));
                    return;
                }

                var body = '<div class="swal-change-list">'
                    + rows.map(function (r) {
                        return '<div class="swal-change">'
                            + '<span class="swal-change-label">' + escapeHtml(r[0]) + '</span>'
                            + '<span class="swal-change-from">' + escapeHtml(r[1]) + '</span>'
                            + '<i class="fas fa-long-arrow-alt-right swal-change-arrow"></i>'
                            + '<span class="swal-change-to">' + escapeHtml(r[2]) + '</span>'
                            + '</div>';
                    }).join('')
                    + '</div>'
                    + '<p class="swal-note">The farmer can create farms up to the new limit '
                    + 'until the new end date. Expiry reminders already sent are cleared, so '
                    + 'they are warned again against the new dates.</p>';

                Swal.fire(dialog({
                    title: 'Confirm these changes',
                    html: body,
                    icon: 'warning',
                    width: 560,
                    showCancelButton: true,
                    confirmButtonText: 'Save changes',
                    cancelButtonText: 'Go back'
                })).then(function (result) {
                    if (result.isConfirmed) {
                        confirmed = true;
                        form.submit();
                    }
                });
            });
        });
    </script>
@endsection
