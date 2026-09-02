@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-water mr-2"></i>{{ $farm->farm_name }}
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('farm-management.farms.index') }}">Farms</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $farm->farm_name }}</li>
                </ol>
            </nav>
        </div>

        {{-- Summary strip --}}
        <div class="row">
            @php
                $cards = [
                    ['Owner', $farm->farmer ? (trim($farm->farmer->first_name . ' ' . $farm->farmer->last_name) ?: 'Farmer #' . $farm->farmer->id) : 'Missing', 'fa-user', 'primary'],
                    ['Tanks', $tanks->count() . ' (' . $tanks->where('status', 1)->count() . ' active)', 'fa-cubes', 'info'],
                    ['Team', $team->where('is_partner', 0)->count() . ' managers, ' . $team->where('is_partner', 1)->count() . ' partners', 'fa-users', 'success'],
                    ['Feed Used', (float) $totalFeedUsed, 'fa-chart-line', 'warning'],
                ];
            @endphp
            @foreach ($cards as [$label, $value, $icon, $colour])
                <div class="col-md-3 mb-3">
                    <div class="card">
                        <div class="card-body d-flex align-items-center">
                            <i class="fas {{ $icon }} fa-2x text-{{ $colour }} mr-3"></i>
                            <div>
                                <small class="text-muted d-block">{{ $label }}</small>
                                <strong>{{ $value }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <ul class="nav nav-tabs card-header-tabs border-0" id="farmTabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-details">Details</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-tanks">Tanks ({{ $tanks->count() }})</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-team">Managers &amp; Partners ({{ $team->count() }})</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-members">Who Has Access ({{ $members->count() }})</a></li>
                    </ul>

                    @permission('farm-management.update')
                        <a href="{{ route('farm-management.farms.edit', $farm->id) }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit mr-1"></i> Edit Farm
                        </a>
                    @endpermission
                </div>

                <div class="tab-content pt-3">
                    {{-- ---------------------------------------------------- Details --}}
                    <div class="tab-pane fade show active" id="tab-details">
                        <table class="table table-sm">
                            <tr><th style="width:220px">Farm ID</th><td>{{ $farm->id }}</td></tr>
                            <tr><th>Farm Name</th><td>{{ $farm->farm_name }}</td></tr>
                            <tr>
                                <th>Owner</th>
                                <td>
                                    @if ($farm->farmer)
                                        {{ trim($farm->farmer->first_name . ' ' . $farm->farmer->last_name) }}
                                        &middot; {{ $farm->farmer->mobile }}
                                        <span class="badge bg-secondary ml-1">Farmer #{{ $farm->farmer->id }}</span>
                                    @else
                                        <span class="text-danger">Owner record missing (farmer_id {{ $farm->farmer_id }})</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    @if ($farm->trashed())
                                        <span class="badge bg-dark">Deleted</span>
                                        <small class="text-muted ml-2">Hidden from the app. Restore it from the farms list.</small>
                                    @elseif ($farm->status)
                                        <span class="badge bg-success">Active</span>
                                        <small class="text-muted ml-2">Visible in the app to the owner and anyone with access.</small>
                                    @else
                                        <span class="badge bg-warning">Inactive</span>
                                        <small class="text-muted ml-2">Hidden from the app; all data is intact.</small>
                                    @endif
                                </td>
                            </tr>
                            <tr><th>Stocking Date</th><td>{{ $farm->stocking_date ? date('d-m-Y', strtotime($farm->stocking_date)) : '-' }}</td></tr>
                            <tr><th>Declared Tanks</th><td>{{ $farm->no_of_tanks ?? '-' }}</td></tr>
                            <tr><th>Store</th><td>{{ $farm->store ?? '-' }}</td></tr>
                            <tr><th>Low Feed Limit</th><td>{{ $farm->low_feed_limit ?? '-' }}</td></tr>
                            <tr><th>Total Feed Used</th><td>{{ (float) $totalFeedUsed }}</td></tr>
                            <tr><th>Created</th><td>{{ optional($farm->created_at)->format('d-m-Y, h:i A') ?? '-' }}</td></tr>
                        </table>
                    </div>

                    {{-- ------------------------------------------------------ Tanks --}}
                    <div class="tab-pane fade" id="tab-tanks">
                        @permission('farm-management.create')
                            <button class="btn btn-sm btn-primary mb-3" data-toggle="collapse" data-target="#addTank">
                                <i class="fas fa-plus mr-1"></i> Add Tank
                            </button>

                            <div class="collapse mb-4" id="addTank">
                                <div class="card card-body bg-light">
                                    <form action="{{ route('farm-management.tanks.store', $farm->id) }}" method="POST">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-3 form-group">
                                                <label>Tank Name</label>
                                                <input type="text" name="tank_name" class="form-control" required>
                                            </div>
                                            <div class="col-md-2 form-group">
                                                <label>Status</label>
                                                <select name="status" class="form-control">
                                                    <option value="1">Active</option>
                                                    <option value="0">Inactive</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2 form-group">
                                                <label>Meals / day</label>
                                                <input type="number" name="meals" class="form-control" min="0">
                                            </div>
                                            <div class="col-md-2 form-group">
                                                <label>Store</label>
                                                <input type="number" step="0.01" name="store" class="form-control" min="0">
                                            </div>
                                            <div class="col-md-3 form-group">
                                                <label>Stocking Date</label>
                                                <input type="date" name="stocking_date" class="form-control"
                                                    value="{{ $farm->stocking_date ? \Illuminate\Support\Carbon::parse($farm->stocking_date)->format('Y-m-d') : '' }}">
                                                <small class="text-muted">Defaults to the farm's stocking date.</small>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus mr-1"></i> Add Tank
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endpermission

                        @if ($tanks->isEmpty())
                            <p class="text-muted mb-0">No tanks recorded for this farm yet.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th><th>Tank</th><th>Status</th><th>Meals</th>
                                            <th>Store</th><th>Feed Used</th><th>Stocking Date</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($tanks as $tank)
                                            <tr>
                                                <td>{{ $tank->id }}</td>
                                                <td>{{ $tank->tank_name }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $tank->status ? 'success' : 'secondary' }}">
                                                        {{ $tank->status ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </td>
                                                <td>{{ $tank->meals ?? '-' }}</td>
                                                <td>{{ $tank->store ?? '-' }}</td>
                                                <td>{{ $tank->total_feed_used ?? 0 }}</td>
                                                <td>{{ $tank->stocking_date ? date('d-m-Y', strtotime($tank->stocking_date)) : '-' }}</td>
                                                <td class="text-center text-nowrap">
                                                    @permission('farm-management.view')
                                                        <a href="{{ route('farm-management.tanks.feed', [$farm->id, $tank->id]) }}"
                                                            class="btn btn-sm btn-info btn-action" title="Feed records">
                                                            <i class="fas fa-utensils"></i>
                                                        </a>
                                                    @endpermission

                                                    @permission('farm-management.update')
                                                        <button class="btn btn-sm btn-primary btn-action" title="Edit tank"
                                                            data-toggle="collapse" data-target="#editTank{{ $tank->id }}">
                                                            <i class="fas fa-edit"></i>
                                                        </button>

                                                        <form action="{{ route('farm-management.tanks.toggle-status', [$farm->id, $tank->id]) }}"
                                                            method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit"
                                                                class="btn btn-sm btn-{{ $tank->status ? 'warning' : 'success' }} btn-action"
                                                                title="{{ $tank->status ? 'Deactivate' : 'Activate' }}">
                                                                <i class="fas fa-power-off"></i>
                                                            </button>
                                                        </form>
                                                    @endpermission

                                                    @permission('farm-management.delete')
                                                        <form action="{{ route('farm-management.tanks.destroy', [$farm->id, $tank->id]) }}"
                                                            method="POST" class="d-inline confirm-form">
                                                            @csrf @method('DELETE')
                                                            <button type="button" class="btn btn-sm btn-danger btn-action confirm-btn"
                                                                data-confirm="Every feed record for this tank is deleted too. This cannot be undone.">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endpermission
                                                </td>
                                            </tr>

                                            @permission('farm-management.update')
                                                <tr class="collapse" id="editTank{{ $tank->id }}">
                                                    <td colspan="7" class="bg-light">
                                                        <form action="{{ route('farm-management.tanks.update', [$farm->id, $tank->id]) }}" method="POST">
                                                            @csrf @method('PUT')
                                                            <div class="row">
                                                                <div class="col-md-3 form-group mb-2">
                                                                    <label class="small mb-1">Tank Name</label>
                                                                    <input type="text" name="tank_name" class="form-control form-control-sm"
                                                                        value="{{ $tank->tank_name }}" required>
                                                                </div>
                                                                <div class="col-md-2 form-group mb-2">
                                                                    <label class="small mb-1">Status</label>
                                                                    <select name="status" class="form-control form-control-sm">
                                                                        <option value="1" @selected($tank->status)>Active</option>
                                                                        <option value="0" @selected(!$tank->status)>Inactive</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-2 form-group mb-2">
                                                                    <label class="small mb-1">Meals / day</label>
                                                                    <input type="number" name="meals" class="form-control form-control-sm"
                                                                        min="0" value="{{ $tank->meals }}">
                                                                </div>
                                                                <div class="col-md-2 form-group mb-2">
                                                                    <label class="small mb-1">Store</label>
                                                                    <input type="number" step="0.01" name="store" class="form-control form-control-sm"
                                                                        min="0" value="{{ $tank->store }}">
                                                                </div>
                                                                <div class="col-md-3 form-group mb-2">
                                                                    <label class="small mb-1">Stocking Date</label>
                                                                    <input type="date" name="stocking_date" class="form-control form-control-sm"
                                                                        value="{{ $tank->stocking_date ? \Illuminate\Support\Carbon::parse($tank->stocking_date)->format('Y-m-d') : '' }}">
                                                                </div>
                                                            </div>
                                                            <button type="submit" class="btn btn-sm btn-primary mb-2">
                                                                <i class="fas fa-save mr-1"></i> Save Tank
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endpermission
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- ------------------------------------------------------- Team --}}
                    <div class="tab-pane fade" id="tab-team">
                        @permission('farm-management.create')
                            <a href="{{ route('farm-management.team.create', ['farm_id' => $farm->id]) }}"
                                class="btn btn-sm btn-primary mb-3">
                                <i class="fas fa-user-plus mr-1"></i> Add Manager / Partner
                            </a>
                        @endpermission

                        @if ($team->isEmpty())
                            <p class="text-muted mb-0">Nobody has been given access to this farm yet.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th><th>Name</th><th>Phone</th><th>Role</th>
                                            <th>Permissions</th><th>Added</th><th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($team as $member)
                                            <tr>
                                                <td>{{ $member->id }}</td>
                                                <td>{{ $member->name }}</td>
                                                <td>{{ $member->phone }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $member->is_partner ? 'secondary' : 'info' }}">
                                                        {{ ucfirst($member->role_label) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @include('admin.farm-management.partials._permission-badges', ['row' => $member])
                                                </td>
                                                <td>{{ optional($member->created_at)->format('d-m-Y') ?? '-' }}</td>
                                                <td class="text-center">
                                                    @permission('farm-management.update')
                                                        <a href="{{ route('farm-management.team.edit', $member->id) }}"
                                                            class="btn btn-sm btn-primary btn-action" title="Edit access">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    @endpermission
                                                    @permission('farm-management.delete')
                                                        <form action="{{ route('farm-management.team.destroy', $member->id) }}"
                                                            method="POST" class="d-inline confirm-form">
                                                            @csrf @method('DELETE')
                                                            <button type="button" class="btn btn-sm btn-danger btn-action confirm-btn"
                                                                data-confirm="They lose access to this farm immediately.">
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

                    {{-- ------------------------------------------- Who Has Access --}}
                    {{-- A membership IS the access — this is the table the
                         server consults when deciding who may open the farm.
                         People get here by being picked directly, now the only
                         way in; the QR + PIN flow that also used to land people
                         here has been removed. --}}
                    <div class="tab-pane fade" id="tab-members">
                        @permission('farm-management.create')
                            <button class="btn btn-sm btn-primary mb-3" data-toggle="collapse" data-target="#addMembers">
                                <i class="fas fa-user-check mr-1"></i> Give Access Directly
                            </button>

                            <div class="collapse mb-4" id="addMembers">
                                <div class="card card-body bg-light">
                                    <form action="{{ route('farm-management.members.store', $farm->id) }}" method="POST">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-6 form-group">
                                                <label>People</label>
                                                <input type="text" class="form-control mb-2" id="memberFilter"
                                                    placeholder="Type a name or mobile number to narrow the list"
                                                    autocomplete="off">
                                                <select name="farmer_ids[]" id="memberSelect" class="form-control" multiple size="8" required>
                                                    @foreach ($farmers as $person)
                                                        <option value="{{ $person->id }}">
                                                            {{ trim($person->first_name . ' ' . $person->last_name) ?: 'Farmer #' . $person->id }}
                                                            — {{ $person->mobile }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted">
                                                    Hold Ctrl (Cmd on Mac) to pick several. They get access straight
                                                    away.
                                                </small>
                                                <small class="text-muted d-block" id="memberFilterCount"></small>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Role</label>
                                                    <select name="role" class="form-control" required>
                                                        <option value="manager">Manager</option>
                                                        <option value="partner">Partner</option>
                                                    </select>
                                                </div>
                                                <label class="d-block"><strong>What they may do</strong></label>
                                                @include('admin.farm-management.partials._permissions', ['values' => ['view_access' => 1]])
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-primary mt-2">
                                            <i class="fas fa-user-check mr-1"></i> Give Access
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endpermission

                        @if ($members->isEmpty())
                            <p class="text-muted mb-0">
                                Nobody holds access to this farm yet. Give someone access using the button
                                above.
                            </p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th><th>Person</th><th>Role</th><th>How</th>
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
                                                    <span class="badge bg-{{ $member->role === 'partner' ? 'secondary' : 'info' }}">
                                                        {{ ucfirst($member->role) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="small">Added directly</span>
                                                    @if ($member->grantedBy)
                                                        <div class="small text-muted">
                                                            by {{ trim($member->grantedBy->first_name . ' ' . $member->grantedBy->last_name) ?: 'Farmer #' . $member->granted_by }}
                                                        </div>
                                                    @endif
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
                                                        <button class="btn btn-sm btn-primary btn-action" title="Edit access"
                                                            data-toggle="collapse" data-target="#editMember{{ $member->id }}">
                                                            <i class="fas fa-edit"></i>
                                                        </button>

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
                                                                data-confirm="This erases the record that they ever had access. Revoke instead if you want to keep the history.">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endpermission
                                                </td>
                                            </tr>

                                            @permission('farm-management.update')
                                                <tr class="collapse" id="editMember{{ $member->id }}">
                                                    <td colspan="7" class="bg-light">
                                                        <form action="{{ route('farm-management.members.update', $member->id) }}" method="POST">
                                                            @csrf @method('PUT')
                                                            <div class="row">
                                                                <div class="col-md-4 form-group mb-2">
                                                                    <label class="small mb-1">Role</label>
                                                                    <select name="role" class="form-control form-control-sm">
                                                                        <option value="manager" @selected($member->role === 'manager')>Manager</option>
                                                                        <option value="partner" @selected($member->role === 'partner')>Partner</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6 form-group mb-2">
                                                                    <label class="small mb-1 d-block">What they may do</label>
                                                                    @include('admin.farm-management.partials._permissions', ['values' => [
                                                                        'view_access'   => $member->view_access,
                                                                        'edit_access'   => $member->edit_access,
                                                                        'create_access' => $member->create_access,
                                                                        'delete_access' => $member->delete_access,
                                                                    ]])
                                                                </div>
                                                            </div>
                                                            <button type="submit" class="btn btn-sm btn-primary mb-2">
                                                                <i class="fas fa-save mr-1"></i> Save Access
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endpermission
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        // Narrow the people list in place. Every farmer is already on the page,
        // so this is a filter rather than a search — no request, and a picked
        // person stays picked even when the filter hides them.
        (function () {
            var filter = document.getElementById('memberFilter');
            var select = document.getElementById('memberSelect');
            var count  = document.getElementById('memberFilterCount');

            if (!filter || !select) {
                return;
            }

            var options = Array.prototype.slice.call(select.options);

            function apply() {
                var term = filter.value.trim().toLowerCase();
                var shown = 0;

                options.forEach(function (option) {
                    var match = !term || option.text.toLowerCase().indexOf(term) !== -1;

                    // Never hide someone already chosen, or they would look
                    // deselected while still being submitted.
                    option.hidden = !match && !option.selected;

                    if (!option.hidden) {
                        shown++;
                    }
                });

                count.textContent = term
                    ? shown + ' of ' + options.length + ' people shown'
                    : '';
            }

            filter.addEventListener('input', apply);
            select.addEventListener('change', apply);
        })();
    </script>

    @include('admin.farm-management.partials._table-scripts', ['entity' => 'records'])
@endpush
