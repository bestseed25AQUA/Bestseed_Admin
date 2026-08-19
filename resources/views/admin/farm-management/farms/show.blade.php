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
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-codes">Access Codes ({{ $grants->count() }})</a></li>
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
                            <tr><th>Stocking Date</th><td>{{ $farm->stocking_date ?? '-' }}</td></tr>
                            <tr><th>Declared Tanks</th><td>{{ $farm->no_of_tanks ?? '-' }}</td></tr>
                            <tr><th>Store</th><td>{{ $farm->store ?? '-' }}</td></tr>
                            <tr><th>Low Feed Limit</th><td>{{ $farm->low_feed_limit ?? '-' }}</td></tr>
                            <tr><th>Total Feed Used</th><td>{{ (float) $totalFeedUsed }}</td></tr>
                            <tr><th>Created</th><td>{{ optional($farm->created_at)->format('d M Y, h:i A') ?? '-' }}</td></tr>
                        </table>
                    </div>

                    {{-- ------------------------------------------------------ Tanks --}}
                    <div class="tab-pane fade" id="tab-tanks">
                        @if ($tanks->isEmpty())
                            <p class="text-muted mb-0">No tanks recorded for this farm yet.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th><th>Tank</th><th>Status</th><th>Meals</th>
                                            <th>Store</th><th>Feed Used</th><th>Stocking Date</th>
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
                                                <td>{{ $tank->stocking_date ?? '-' }}</td>
                                            </tr>
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
                                                <td>{{ optional($member->created_at)->format('d M Y') ?? '-' }}</td>
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
                                                                data-confirm="They lose access immediately, and the QR code that created them is revoked.">
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

                    {{-- ----------------------------------------------- Access Codes --}}
                    <div class="tab-pane fade" id="tab-codes">
                        @permission('farm-management.create')
                            <button class="btn btn-sm btn-primary mb-3" data-toggle="collapse" data-target="#generateCode">
                                <i class="fas fa-qrcode mr-1"></i> Generate Access Code
                            </button>

                            <div class="collapse mb-4" id="generateCode">
                                <div class="card card-body bg-light">
                                    <form action="{{ route('farm-management.grants.store') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="farm_id" value="{{ $farm->id }}">

                                        <div class="row">
                                            <div class="col-md-4 form-group">
                                                <label>Role</label>
                                                <select name="role" class="form-control" required>
                                                    <option value="manager">Manager</option>
                                                    <option value="partner">Partner</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Valid for (days)</label>
                                                <input type="number" name="duration_days" class="form-control"
                                                    min="1" max="365" value="30" required>
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>4-digit PIN</label>
                                                <input type="text" name="pin" class="form-control"
                                                    pattern="\d{4}" maxlength="4" placeholder="e.g. 1234" required>
                                                <small class="text-muted">The scanner must type this after scanning.</small>
                                            </div>
                                        </div>

                                        <label class="d-block"><strong>What they may do</strong></label>
                                        @include('admin.farm-management.partials._permissions', ['values' => ['view_access' => 1]])

                                        <button type="submit" class="btn btn-primary mt-2">
                                            <i class="fas fa-qrcode mr-1"></i> Generate
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endpermission

                        @if ($grants->isEmpty())
                            <p class="text-muted mb-0">No access codes have been issued for this farm.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th><th>Role</th><th>Status</th><th>PIN</th>
                                            <th>Permissions</th><th>Expires</th><th>Redeemed By</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($grants as $grant)
                                            <tr>
                                                <td>{{ $grant->id }}</td>
                                                <td><span class="badge bg-{{ $grant->role === 'partner' ? 'secondary' : 'info' }}">{{ ucfirst($grant->role) }}</span></td>
                                                <td>@include('admin.farm-management.partials._grant-status', ['grant' => $grant])</td>
                                                <td><code>{{ $grant->pin() ?? '—' }}</code></td>
                                                <td>@include('admin.farm-management.partials._permission-badges', ['row' => $grant])</td>
                                                <td>
                                                    {{ optional($grant->expires_at)->format('d M Y') ?? '—' }}
                                                    <small class="d-block text-muted">{{ $grant->daysRemaining() }} days left</small>
                                                </td>
                                                <td>{{ $grant->manager->name ?? '—' }}</td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-info btn-action show-qr"
                                                        data-token="{{ $grant->token }}" data-grant="{{ $grant->id }}"
                                                        title="Show QR">
                                                        <i class="fas fa-qrcode"></i>
                                                    </button>

                                                    @permission('farm-management.update')
                                                        @if (!$grant->revoked_at)
                                                            <form action="{{ route('farm-management.grants.revoke', $grant->id) }}"
                                                                method="POST" class="d-inline confirm-form">
                                                                @csrf
                                                                <button type="button" class="btn btn-sm btn-warning btn-action confirm-btn"
                                                                    data-confirm="The holder loses access immediately. The record is kept for audit."
                                                                    title="Revoke">
                                                                    <i class="fas fa-ban"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endpermission

                                                    @permission('farm-management.delete')
                                                        <form action="{{ route('farm-management.grants.destroy', $grant->id) }}"
                                                            method="POST" class="d-inline confirm-form">
                                                            @csrf @method('DELETE')
                                                            <button type="button" class="btn btn-sm btn-danger btn-action confirm-btn"
                                                                data-confirm="The code is deleted permanently, with no audit trail."
                                                                title="Delete">
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
        </div>
    </div>

    @include('admin.farm-management.partials._qr-modal')
@endsection

@push('scripts')
    @include('admin.farm-management.partials._table-scripts', ['entity' => 'records'])
    @include('admin.farm-management.partials._qr-script')
@endpush
