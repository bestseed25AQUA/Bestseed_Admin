@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title d-flex align-items-center">
            <i class="fas fa-box-open mr-2"></i> Edit Package
        </h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('subscription-plans.index') }}">Packages</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Package</li>
            </ol>
        </nav>
    </div>

    @include('admin.partials.flash')

    <div class="card">
        <div class="card-body">
            @if (isset($sold) && $sold > 0)
                <div class="alert alert-info py-2 px-3 small">
                    <i class="fas fa-info-circle mr-1"></i>
                    {{ $sold }} subscription(s) have been sold from this package.
                    Changing it here affects only <strong>future</strong> sales — each one
                    already sold keeps the farms, price and length it was sold under.
                </div>
            @endif

            <form action="{{ route('subscription-plans.update', $plan) }}" method="POST" id="planForm">
                @method('PUT')
                @include('admin.subscription-plans._form')
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('planForm');
        if (!form) return;

        @php
            $originalValues = [
                'label'      => (string) $plan->label,
                'months'     => (string) $plan->months,
                'farm_limit' => (string) $plan->farm_limit,
                'amount'     => number_format((float) $plan->amount, 2, '.', ''),
                'reminders'  => is_array($plan->reminders) ? implode(', ', $plan->reminders) : '',
                'sort_order' => (string) ($plan->sort_order ?: $plan->months),
                'is_active'  => (bool) $plan->is_active,
            ];
        @endphp

        var currency = @json(config('subscriptions.currency_symbol'));
        var sold     = {{ (int) ($sold ?? 0) }};
        var live     = {{ (int) $plan->subscriptions()->active()->count() }};
        var original = @json($originalValues);

        var labels = {
            label:      'Name',
            months:     'Duration (months)',
            farm_limit: 'Farms granted',
            amount:     'Price',
            reminders:  'Warns from (days)',
            sort_order: 'Sort order',
            is_active:  'On sale'
        };

        function field(name) {
            return form.querySelector('[name="' + name + '"]:not([type="hidden"])');
        }

        function currentValue(name) {
            var el = field(name);
            if (!el) return null;
            if (el.type === 'checkbox') return el.checked;
            if (name === 'amount') return parseFloat(el.value || 0).toFixed(2);
            return el.value.trim();
        }

        function display(name, value) {
            if (name === 'is_active') return value ? 'Yes' : 'No';
            if (name === 'amount') return currency + value;
            return value === '' ? '—' : value;
        }

        function escapeHtml(value) {
            var div = document.createElement('div');
            div.textContent = value == null ? '' : String(value);
            return div.innerHTML;
        }

        function changes() {
            var rows = [];

            Object.keys(labels).forEach(function (name) {
                var now = currentValue(name);
                if (now === null) return;

                var was = original[name];
                if (name === 'is_active') was = !!was;

                if (String(now) !== String(was)) {
                    rows.push([labels[name], display(name, was), display(name, now)]);
                }
            });

            return rows;
        }

        var confirmed = false;

        form.addEventListener('submit', function (event) {
            if (confirmed) return;
            event.preventDefault();

            var rows = changes();

            if (rows.length === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Nothing changed',
                    text: 'Edit something first, or press Cancel to go back.'
                });
                return;
            }

            var table = '<table class="table table-sm mb-3"><thead><tr>'
                + '<th class="text-left">What</th><th class="text-left">Now</th>'
                + '<th class="text-left">After saving</th></tr></thead><tbody>'
                + rows.map(function (r) {
                    return '<tr><td class="text-left">' + escapeHtml(r[0])
                        + '</td><td class="text-left text-muted">' + escapeHtml(r[1])
                        + '</td><td class="text-left"><strong>' + escapeHtml(r[2])
                        + '</strong></td></tr>';
                }).join('')
                + '</tbody></table>';

            var who = sold === 0
                ? '<div class="alert alert-secondary text-left small mb-0">'
                    + 'Nothing has been sold on this package yet, so this applies to every '
                    + 'future sale.</div>'
                : '<div class="alert alert-warning text-left small mb-0">'
                    + '<strong>Applies to NEW sales only.</strong><br>'
                    + sold + ' subscription(s) have already been sold on this package'
                    + (live > 0 ? ', ' + live + ' of them still live' : '')
                    + '. Each keeps the farms, price and length it was sold under. '
                    + 'To give an existing farmer the new terms, edit their subscription '
                    + 'or record another one.</div>';

            Swal.fire({
                title: 'Confirm these changes',
                html: '<div class="text-left">' + table + who + '</div>',
                icon: 'warning',
                width: 660,
                showCancelButton: true,
                confirmButtonText: 'Yes, save package',
                cancelButtonText: 'Go back'
            }).then(function (result) {
                if (result.isConfirmed) {
                    confirmed = true;
                    form.submit();
                }
            });
        });
    });
</script>
@endsection
