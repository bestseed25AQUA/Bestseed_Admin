@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-qrcode mr-2"></i>Access Codes (QR)
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Access Codes</li>
                </ol>
            </nav>
        </div>

        @permission('farm-management.create')
            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="card-title">Generate a new access code</h4>
                    <p class="text-muted">
                        The QR carries only an opaque token — the role, permissions, expiry and PIN stay on
                        the server, so a code that has already been shared can still be revoked.
                    </p>

                    <form action="{{ route('farm-management.grants.store') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label>Farm <span class="text-danger">*</span></label>
                                <select name="farm_id" class="form-control" required>
                                    <option value="">-- choose a farm --</option>
                                    @foreach ($farms as $farm)
                                        <option value="{{ $farm->id }}" {{ old('farm_id') == $farm->id ? 'selected' : '' }}>
                                            {{ $farm->farm_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Role <span class="text-danger">*</span></label>
                                <select name="role" class="form-control" required>
                                    <option value="manager" {{ old('role') === 'manager' ? 'selected' : '' }}>Manager</option>
                                    <option value="partner" {{ old('role') === 'partner' ? 'selected' : '' }}>Partner</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Valid for (days) <span class="text-danger">*</span></label>
                                <input type="number" name="duration_days" class="form-control" min="1" max="365"
                                    value="{{ old('duration_days', 30) }}" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>4-digit PIN <span class="text-danger">*</span></label>
                                <input type="text" name="pin" class="form-control" pattern="\d{4}" maxlength="4"
                                    placeholder="e.g. 1234" value="{{ old('pin') }}" required>
                            </div>
                        </div>

                        <label class="d-block"><strong>What the holder may do</strong></label>
                        @include('admin.farm-management.partials._permissions', ['values' => ['view_access' => 1]])

                        <button type="submit" class="btn btn-primary mt-2">
                            <i class="fas fa-qrcode mr-1"></i> Generate Code
                        </button>
                    </form>
                </div>
            </div>
        @endpermission

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">All access codes <span class="badge bg-primary ml-2">{{ $grants->count() }}</span></h4>
                </div>

                <form method="GET" class="form-inline mb-3">
                    <label class="mr-2 mb-0">Farm</label>
                    <select name="farm_id" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
                        <option value="">All farms</option>
                        @foreach ($farms as $farm)
                            <option value="{{ $farm->id }}" {{ request('farm_id') == $farm->id ? 'selected' : '' }}>
                                {{ $farm->farm_name }}
                            </option>
                        @endforeach
                    </select>

                    <label class="mr-2 mb-0">Role</label>
                    <select name="role" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
                        <option value="">Both</option>
                        <option value="manager" {{ request('role') === 'manager' ? 'selected' : '' }}>Manager</option>
                        <option value="partner" {{ request('role') === 'partner' ? 'selected' : '' }}>Partner</option>
                    </select>

                    <label class="mr-2 mb-0">Status</label>
                    <select name="status" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        <option value="">Any</option>
                        <option value="live" {{ request('status') === 'live' ? 'selected' : '' }}>Live</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Not scanned yet</option>
                        <option value="revoked" {{ request('status') === 'revoked' ? 'selected' : '' }}>Revoked</option>
                    </select>

                    @if (request('farm_id') || request('role') || request('status'))
                        <a href="{{ route('farm-management.grants.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th><th>Farm</th><th>Role</th><th>Status</th><th>PIN</th>
                                <th>Permissions</th><th>Expires</th><th>Scanned By</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grants as $grant)
                                <tr>
                                    <td class="font-weight-bold text-primary">{{ $grant->id }}</td>
                                    <td>
                                        @if ($grant->farm)
                                            <a href="{{ route('farm-management.farms.show', $grant->farm->id) }}">
                                                {{ $grant->farm->farm_name }}
                                            </a>
                                            @if ($grant->farm->farmer)
                                                <small class="d-block text-muted">
                                                    owner: {{ trim($grant->farm->farmer->first_name . ' ' . $grant->farm->farmer->last_name) }}
                                                </small>
                                            @endif
                                        @else
                                            <span class="text-danger">Farm deleted</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $grant->role === 'partner' ? 'secondary' : 'info' }}">
                                            {{ ucfirst($grant->role) }}
                                        </span>
                                    </td>
                                    <td>@include('admin.farm-management.partials._grant-status', ['grant' => $grant])</td>
                                    <td><code>{{ $grant->pin() ?? '—' }}</code></td>
                                    <td>@include('admin.farm-management.partials._permission-badges', ['row' => $grant])</td>
                                    <td>
                                        {{ optional($grant->expires_at)->format('d M Y') ?? '—' }}
                                        <small class="d-block text-muted">{{ $grant->daysRemaining() }} days left</small>
                                    </td>
                                    <td>
                                        @if ($grant->redeemed_at)
                                            {{ $grant->manager->name ?? 'Unknown' }}
                                            @php $scanner = $scanners->get($grant->redeemed_by); @endphp
                                            @if ($scanner)
                                                <small class="d-block text-muted">
                                                    {{ trim($scanner->first_name . ' ' . $scanner->last_name) }} · {{ $scanner->mobile }}
                                                </small>
                                            @endif
                                            <small class="d-block text-muted">
                                                {{ optional($grant->redeemed_at)->format('d M Y, h:i A') }}
                                            </small>
                                        @else
                                            <span class="text-muted">Not scanned yet</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <button class="btn btn-sm btn-info btn-action show-qr"
                                                data-token="{{ $grant->token }}" data-grant="{{ $grant->id }}" title="Show QR">
                                                <i class="fas fa-qrcode"></i>
                                            </button>

                                            @permission('farm-management.update')
                                                @if (!$grant->revoked_at)
                                                    <form action="{{ route('farm-management.grants.revoke', $grant->id) }}"
                                                        method="POST" class="d-inline confirm-form ml-1">
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
                                                    method="POST" class="d-inline confirm-form ml-1">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="btn btn-sm btn-danger btn-action confirm-btn"
                                                        data-confirm="The code is deleted permanently, with no audit trail."
                                                        title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endpermission
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('admin.farm-management.partials._qr-modal')
@endsection

@push('scripts')
    @include('admin.farm-management.partials._table-scripts', ['entity' => 'access codes'])
    @include('admin.farm-management.partials._qr-script')
@endpush

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/bootstrap4.min.css') }}">
@endpush
