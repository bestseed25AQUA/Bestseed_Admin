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
                            <option value="expired" @selected(request('status') === 'expired')>Expired</option>
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
                    <p class="text-muted mb-0">
                        Nobody holds farm access matching this filter. Give access from a farm's
                        <strong>Who Has Access</strong> tab.
                    </p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th><th>Person</th><th>Farm</th><th>Role</th><th>How</th>
                                    <th>Permissions</th><th>Status</th><th>Expires</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $member)
                                    @php
                                        $person = $member->farmer;
                                        $live   = $member->isLive();
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
                                            @elseif (!$live)
                                                <span class="badge bg-warning">Expired</span>
                                            @else
                                                <span class="badge bg-success">Active</span>
                                            @endif
                                        </td>
                                        <td>{{ $member->expires_at ? $member->expires_at->format('d M Y') : 'Never' }}</td>
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
