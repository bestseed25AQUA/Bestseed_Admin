@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-user-check mr-2"></i>Who Has Access
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Who Has Access</li>
                </ol>
            </nav>
        </div>

        {{-- A membership IS the access — this is the table the server consults when
             deciding who may open a farm, and the only list that shows
             everyone who can. --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="form-row align-items-end">
                    <div class="col-md-4 form-group mb-2">
                        <label class="small mb-1">Farm</label>
                        <select name="farm_id" class="form-control">
                            <option value="">All farms</option>
                            @foreach ($farms as $farm)
                                <option value="{{ $farm->id }}" @selected(request('farm_id') == $farm->id)>
                                    {{ $farm->farm_name }}
                                    @if ($farm->farmer)
                                        — {{ trim($farm->farmer->first_name . ' ' . $farm->farmer->last_name) }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group mb-2">
                        <label class="small mb-1">Role</label>
                        <select name="role" class="form-control">
                            <option value="">Any role</option>
                            <option value="manager" @selected(request('role') === 'manager')>Manager</option>
                            <option value="partner" @selected(request('role') === 'partner')>Partner</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group mb-2">
                        <label class="small mb-1">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Any status</option>
                            <option value="live" @selected(request('status') === 'live')>Active</option>
                            <option value="revoked" @selected(request('status') === 'revoked')>Revoked</option>
                        </select>
                    </div>
                    <div class="col-md-2 form-group mb-2">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-filter mr-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                @if ($members->isEmpty())
                    {{-- Empty state.
                         "Revoked" being empty is good news; "live" being empty
                         while revoked rows exist means every grant has been
                         taken back, which is worth saying plainly rather than
                         reporting as the same nothing. --}}
                    @php
                        $noMembersAtAll = ($totalMembers ?? 0) === 0;
                        $statusFilter   = request()->input('status');
                        $isFiltered     = request()->filled('farm_id') || request()->filled('role');

                        $empty = $noMembersAtAll
                            ? [
                                'icon'  => 'fa-user-friends',
                                'tone'  => 'secondary',
                                'title' => 'Nobody has farm access yet',
                                'body'  => 'Owners work their own farms alone until they share them. '
                                         . 'Give access from a farm\'s Who Has Access tab, or let the '
                                         . 'farmer add a manager or partner from the app.',
                            ]
                            : ($isFiltered
                                ? [
                                    'icon'  => 'fa-filter',
                                    'tone'  => 'secondary',
                                    'title' => 'Nobody matches this filter',
                                    'body'  => 'No manager or partner matches the farm and role you '
                                             . 'picked.',
                                ]
                                : match ($statusFilter) {
                                    'revoked' => [
                                        'icon'  => 'fa-check-circle',
                                        'tone'  => 'success',
                                        'title' => 'Nothing revoked',
                                        'body'  => 'No access has been taken back. Revoked grants are '
                                                 . 'kept here as a record of who had access and when.',
                                    ],
                                    'live' => [
                                        'icon'  => 'fa-user-slash',
                                        'tone'  => 'warning',
                                        'title' => 'No live access',
                                        'body'  => 'Every grant has been revoked, so no manager or '
                                                 . 'partner can open a farm right now.',
                                    ],
                                    default => [
                                        'icon'  => 'fa-filter',
                                        'tone'  => 'secondary',
                                        'title' => 'Nobody matches this filter',
                                        'body'  => 'Try clearing the filters above.',
                                    ],
                                });
                    @endphp

                    <div class="text-center py-5">
                        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center
                                    rounded-circle bg-light"
                             style="width:84px; height:84px;">
                            <i class="fas {{ $empty['icon'] }} fa-2x text-{{ $empty['tone'] }}"></i>
                        </div>

                        <h5 class="mb-2">{{ $empty['title'] }}</h5>

                        <p class="text-muted mb-0 mx-auto" style="max-width:480px;">
                            {{ $empty['body'] }}
                        </p>

                        @if (!$noMembersAtAll)
                            <a href="{{ route('farm-management.members.index') }}"
                               class="btn btn-sm btn-outline-primary mt-3">
                                Show everyone
                            </a>
                        @endif
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th><th>Person</th><th>Farm</th><th>Role</th><th>How</th>
                                    <th>Permissions</th><th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $member)
                                    @php
                                        $person = $member->farmer;
                                    @endphp
                                    <tr>
                                        <td>{{ $member->id }}</td>
                                        <td>
                                            {{ $person ? (trim($person->first_name . ' ' . $person->last_name) ?: 'Farmer #' . $person->id) : 'Farmer #' . $member->farmer_id }}
                                            <div class="small text-muted">{{ optional($person)->mobile }}</div>
                                        </td>
                                        <td>
                                            @if ($member->farm)
                                                <a href="{{ route('farm-management.farms.show', $member->farm_id) }}">
                                                    {{ $member->farm->farm_name }}
                                                </a>
                                            @else
                                                <span class="text-muted">Farm #{{ $member->farm_id }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $member->role === 'partner' ? 'secondary' : 'info' }}">
                                                {{ ucfirst($member->role) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="small">
                                                Added directly
                                            </span>
                                        </td>
                                        <td>
                                            @include('admin.farm-management.partials._permission-badges', ['row' => $member])
                                        </td>
                                        <td>
                                            @if ($member->revoked_at)
                                                <span class="badge bg-danger">Revoked</span>
                                            @else
                                                <span class="badge bg-success">Active</span>
                                            @endif
                                        </td>
                                        <td class="text-center text-nowrap">
                                            @permission('farm-management.update')
                                                @if ($member->revoked_at)
                                                    <form action="{{ route('farm-management.members.restore', $member->id) }}"
                                                        method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success btn-action" title="Restore access">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('farm-management.members.revoke', $member->id) }}"
                                                        method="POST" class="d-inline confirm-form">
                                                        @csrf
                                                        <button type="button" class="btn btn-sm btn-warning btn-action confirm-btn"
                                                            data-confirm="They lose access to this farm immediately.">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endpermission

                                            @permission('farm-management.delete')
                                                <form action="{{ route('farm-management.members.destroy', $member->id) }}"
                                                    method="POST" class="d-inline confirm-form">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="btn btn-sm btn-danger btn-action confirm-btn"
                                                        data-confirm="This erases the record that they ever had access. Revoke instead to keep the history.">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endpermission
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

@push('scripts')
    @include('admin.farm-management.partials._table-scripts', ['entity' => 'members'])
@endpush
