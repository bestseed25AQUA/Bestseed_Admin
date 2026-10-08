@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title d-flex align-items-center">
            <i class="fas fa-inbox mr-2"></i> Subscription Requests
        </h3>
        <a href="{{ route('subscriptions.index') }}" class="btn btn-sm btn-outline-primary float-right">
            Subscriptions
        </a>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('subscriptions.index') }}">Subscriptions</a></li>
                <li class="breadcrumb-item active" aria-current="page">Requests</li>
            </ol>
        </nav>
    </div>

    @include('admin.partials.flash')

    <p class="text-muted small">
        Farmers who asked from inside the app instead of ringing. Each one names the farm and
        package they were looking at, so there is nothing to ask before acting on it.
    </p>

    <ul class="nav nav-pills mb-3">
        @foreach (['open' => 'Open', 'pending' => 'Not yet contacted', 'done' => 'Done', 'declined' => 'Declined', 'all' => 'All'] as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $status === $key ? 'active' : '' }}"
                   href="{{ route('subscriptions.requests', ['status' => $key]) }}">
                    {{ $label }}
                    @if ($key === 'open' && $counts['open'] > 0)
                        <span class="badge bg-light text-dark ml-1">{{ $counts['open'] }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="card-body">
            @if ($requests->isEmpty())
                {{-- Empty state.
                     Worded per filter, because an empty list means opposite
                     things depending on which one is showing: no OPEN requests
                     is good news and needs no action, while no requests AT ALL
                     usually means the app side is not reaching this screen. A
                     flat "Nothing here." said neither. --}}
                @php
                    $nothingEverAsked = ($counts['total'] ?? 0) === 0;

                    $empty = $nothingEverAsked
                        ? [
                            'icon'  => 'fa-inbox',
                            'tone'  => 'secondary',
                            'title' => 'No requests yet',
                            'body'  => 'When a farmer taps "Ask us to call you" on the '
                                     . 'subscription screen, their request lands here with the '
                                     . 'farm and package already filled in.',
                        ]
                        : match ($status) {
                            'open' => [
                                'icon'  => 'fa-check-circle',
                                'tone'  => 'success',
                                'title' => 'Nobody is waiting',
                                'body'  => 'Every request has been dealt with. New ones appear '
                                         . 'here as soon as a farmer asks from the app.',
                            ],
                            'pending' => [
                                'icon'  => 'fa-phone-volume',
                                'tone'  => 'success',
                                'title' => 'Everyone has been contacted',
                                'body'  => 'No request is still waiting for a first call.',
                            ],
                            'done' => [
                                'icon'  => 'fa-clipboard-check',
                                'tone'  => 'secondary',
                                'title' => 'Nothing completed yet',
                                'body'  => 'Requests you mark as done are kept here as a record '
                                         . 'of what was sold and when.',
                            ],
                            'declined' => [
                                'icon'  => 'fa-ban',
                                'tone'  => 'secondary',
                                'title' => 'Nothing declined',
                                'body'  => 'Requests you turn down are kept here rather than '
                                         . 'deleted, so the decision stays on record.',
                            ],
                            default => [
                                'icon'  => 'fa-inbox',
                                'tone'  => 'secondary',
                                'title' => 'No requests match this filter',
                                'body'  => 'Try another tab above.',
                            ],
                        };
                @endphp

                <div class="text-center py-5">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center
                                rounded-circle bg-light"
                         style="width:84px; height:84px;">
                        <i class="fas {{ $empty['icon'] }} fa-2x text-{{ $empty['tone'] }}"></i>
                    </div>

                    <h5 class="mb-2">{{ $empty['title'] }}</h5>

                    {{-- Narrow on purpose: a line of help text running the full
                         width of a desktop table is hard to read. --}}
                    <p class="text-muted mb-0 mx-auto" style="max-width:460px;">
                        {{ $empty['body'] }}
                    </p>

                    {{-- Offered only when another tab actually holds something,
                         so it is never a link to a second empty page. --}}
                    @if (!$nothingEverAsked && $status !== 'all')
                        <a href="{{ route('subscriptions.requests', ['status' => 'all']) }}"
                           class="btn btn-sm btn-outline-primary mt-3">
                            See all requests
                        </a>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Farmer</th><th>Farm</th><th>Package asked for</th>
                                <th>Message</th><th>Asked</th><th>Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $req)
                                <tr class="{{ $req->row_class }}">
                                    <td>
                                        {{ trim(($req->farmer->first_name ?? '') . ' ' . ($req->farmer->last_name ?? '')) ?: 'Farmer #' . $req->farmer_id }}
                                        <div class="small text-muted">{{ $req->farmer->mobile ?? '' }}</div>
                                    </td>
                                    <td>
                                        @if ($req->farm)
                                            <a href="{{ route('farm-management.farms.show', $req->farm_id) }}">{{ $req->farm->farm_name }}</a>
                                        @else
                                            {{-- No farm named: they are asking for a new one
                                                 rather than about one they already have. --}}
                                            <span class="text-muted">A new farm</span>
                                        @endif
                                    </td>
                                    <td>{{ $req->plan_label ?? '—' }}</td>
                                    <td class="small">{{ $req->message ?: '—' }}</td>
                                    <td class="small">{{ $req->created_at?->format('d M Y, H:i') }}</td>
                                    <td>
                                        @switch($req->status)
                                            @case('pending')   <span class="badge bg-warning text-dark">Not contacted</span> @break
                                            @case('contacted') <span class="badge bg-info">Contacted</span> @break
                                            @case('done')      <span class="badge bg-success">Done</span> @break
                                            @default           <span class="badge bg-secondary">Declined</span>
                                        @endswitch
                                        @if ($req->admin_note)
                                            <div class="small text-muted mt-1">{{ $req->admin_note }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right text-nowrap">
                                        @if (!$req->is_open)
                                            <span class="text-muted small">No action needed</span>
                                        @else
                                        {{-- Selling is done on the subscriptions screen; this
                                             only records what happened to the request, so the
                                             queue reflects reality. --}}
                                        <a class="btn btn-sm btn-primary"
                                           href="{{ route('subscriptions.create', array_filter([
                                               'farmer' => $req->farmer_id,
                                               'farm'   => $req->farm_id,
                                               'plan'   => $req->plan?->key,
                                           ])) }}">
                                            Sell
                                        </a>
                                        <button class="btn btn-sm btn-secondary"
                                                data-toggle="collapse" data-target="#req{{ $req->id }}">
                                            Update
                                        </button>
                                        @endif
                                    </td>
                                </tr>
                                <tr class="collapse" id="req{{ $req->id }}">
                                    <td colspan="7" class="bg-light">
                                        <form action="{{ route('subscriptions.requests.update', $req) }}" method="POST">
                                            @csrf @method('PUT')
                                            <div class="row align-items-end">
                                                <div class="col-md-3 form-group mb-2">
                                                    <label class="small mb-1">Status</label>
                                                    <select name="status" class="form-control form-control-sm">
                                                        @foreach (['pending' => 'Not contacted', 'contacted' => 'Contacted', 'done' => 'Done', 'declined' => 'Declined'] as $v => $l)
                                                            <option value="{{ $v }}" @selected($req->status === $v)>{{ $l }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-7 form-group mb-2">
                                                    <label class="small mb-1">Note</label>
                                                    <input type="text" name="admin_note" class="form-control form-control-sm"
                                                           value="{{ $req->admin_note }}"
                                                           placeholder="Rang on the 3rd, paying next week…">
                                                </div>
                                                <div class="col-md-2 form-group mb-2">
                                                    <button class="btn btn-sm btn-primary btn-block">Save</button>
                                                </div>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $requests->links() }}
            @endif
        </div>
    </div>
</div>
@endsection
