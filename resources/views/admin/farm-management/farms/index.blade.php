@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-water mr-2"></i>Farms
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Farm Management</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">All Farms <span class="badge bg-primary ml-2">{{ $farms->count() }}</span></h4>
                    @permission('farm-management.create')
                        <a href="{{ route('farm-management.farms.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus-circle mr-1"></i> Add Farm
                        </a>
                    @endpermission
                </div>

                <form method="GET" class="form-inline mb-3">
                    <label class="mr-2 mb-0">Owner</label>
                    <select name="farmer_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        <option value="">All farmers</option>
                        @foreach ($farmers as $farmer)
                            <option value="{{ $farmer->id }}" {{ request('farmer_id') == $farmer->id ? 'selected' : '' }}>
                                {{ trim($farmer->first_name . ' ' . $farmer->last_name) ?: 'Farmer #' . $farmer->id }}
                                ({{ $farmer->mobile }})
                            </option>
                        @endforeach
                    </select>
                    <label class="mr-2 mb-0">Status</label>
                    <select name="status" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        <option value="">Active &amp; inactive</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active only</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive only</option>
                        <option value="deleted" {{ request('status') === 'deleted' ? 'selected' : '' }}>Deleted</option>
                    </select>

                    @if (request('farmer_id') || request('status'))
                        <a href="{{ route('farm-management.farms.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Farm</th>
                                <th>Status</th>
                                <th>Owner</th>
                                <th>Tanks</th>
                                <th>Team</th>
                                <th>Has Access</th>
                                <th>Stocking Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($farms as $farm)
                                <tr>
                                    <td class="font-weight-bold text-primary">{{ $farm->id }}</td>
                                    <td>
                                        <a href="{{ route('farm-management.farms.show', $farm->id) }}">
                                            {{ $farm->farm_name }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($farm->trashed())
                                            <span class="badge bg-dark">Deleted</span>
                                        @elseif ($farm->status)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-warning">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($farm->farmer)
                                            {{ trim($farm->farmer->first_name . ' ' . $farm->farmer->last_name) ?: 'Farmer #' . $farm->farmer->id }}
                                            <small class="d-block text-muted">{{ $farm->farmer->mobile }}</small>
                                        @else
                                            <span class="text-danger">Owner missing (#{{ $farm->farmer_id }})</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $farm->tanks_count }}
                                        <small class="text-muted">({{ $farm->active_tanks_count }} active)</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $farm->managers_count }} mgr</span>
                                        <span class="badge bg-secondary">{{ $farm->partners_count }} ptnr</span>
                                    </td>
                                    <td>
                                        {{ $farm->access_members_count }}
                                        <small class="text-muted">({{ $farm->live_members_count }} live)</small>
                                    </td>
                                    <td>{{ $farm->stocking_date ?? '-' }}</td>
                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <a href="{{ route('farm-management.farms.show', $farm->id) }}"
                                                class="btn btn-sm btn-info btn-action" title="View farm">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            @permission('farm-management.update')
                                                <a href="{{ route('farm-management.farms.edit', $farm->id) }}"
                                                    class="btn btn-sm btn-primary btn-action ml-1" title="Edit farm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @endpermission

                                            @permission('farm-management.update')
                                                @if (!$farm->trashed())
                                                    <form action="{{ route('farm-management.farms.toggle-status', $farm->id) }}"
                                                        method="POST" class="d-inline confirm-form ml-1">
                                                        @csrf
                                                        <button type="button"
                                                            class="btn btn-sm btn-{{ $farm->status ? 'warning' : 'success' }} btn-action confirm-btn"
                                                            data-confirm="{{ $farm->status
                                                                ? 'The farm will be hidden from the app for its owner and everyone with access.'
                                                                : 'The farm becomes visible in the app again.' }}"
                                                            title="{{ $farm->status ? 'Set inactive' : 'Set active' }}">
                                                            <i class="fas fa-{{ $farm->status ? 'toggle-on' : 'toggle-off' }}"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('farm-management.farms.restore', $farm->id) }}"
                                                        method="POST" class="d-inline confirm-form ml-1">
                                                        @csrf
                                                        <button type="button" class="btn btn-sm btn-success btn-action confirm-btn"
                                                            data-confirm="The farm comes back with its team intact. Previous access stays revoked."
                                                            title="Restore farm">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endpermission

                                            @permission('farm-management.delete')
                                                @if (!$farm->trashed())
                                                    <form action="{{ route('farm-management.farms.destroy', $farm->id) }}"
                                                        method="POST" class="d-inline confirm-form ml-1">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-danger btn-action confirm-btn"
                                                            data-confirm="The farm is removed from the app and everyone’s access is revoked. Its team is kept so it can be restored."
                                                            title="Delete farm">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('farm-management.farms.force-destroy', $farm->id) }}"
                                                        method="POST" class="d-inline confirm-form ml-1">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-dark btn-action confirm-btn"
                                                            data-confirm="Permanent. The farm, its team and all access are erased and cannot be restored."
                                                            title="Delete permanently">
                                                            <i class="fas fa-times-circle"></i>
                                                        </button>
                                                    </form>
                                                @endif
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
@endsection

@push('scripts')
    @include('admin.farm-management.partials._table-scripts', ['entity' => 'farms'])
@endpush

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/bootstrap4.min.css') }}">
@endpush
