@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-users mr-2"></i>Managers &amp; Partners
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Managers &amp; Partners</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">
                        People with farm access <span class="badge bg-primary ml-2">{{ $team->count() }}</span>
                    </h4>
                    @permission('farm-management.create')
                        <a href="{{ route('farm-management.team.create') }}" class="btn btn-primary">
                            <i class="fas fa-user-plus mr-1"></i> Add Manager / Partner
                        </a>
                    @endpermission
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
                    <select name="role" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        <option value="">Both</option>
                        <option value="manager" {{ request('role') === 'manager' ? 'selected' : '' }}>Managers</option>
                        <option value="partner" {{ request('role') === 'partner' ? 'selected' : '' }}>Partners</option>
                    </select>

                    @if (request('farm_id') || request('role'))
                        <a href="{{ route('farm-management.team.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th><th>Name</th><th>Phone</th><th>Role</th>
                                <th>Farm</th><th>Permissions</th><th>Added</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($team as $member)
                                <tr>
                                    <td class="font-weight-bold text-primary">{{ $member->id }}</td>
                                    <td>{{ $member->name }}</td>
                                    <td>{{ $member->phone }}</td>
                                    <td>
                                        <span class="badge bg-{{ $member->is_partner ? 'secondary' : 'info' }}">
                                            {{ ucfirst($member->role_label) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($member->farm)
                                            <a href="{{ route('farm-management.farms.show', $member->farm->id) }}">
                                                {{ $member->farm->farm_name }}
                                            </a>
                                        @else
                                            <span class="text-warning" title="Legacy row created before farms were scoped">
                                                Not linked to a farm
                                            </span>
                                        @endif
                                    </td>
                                    <td>@include('admin.farm-management.partials._permission-badges', ['row' => $member])</td>
                                    <td>{{ optional($member->created_at)->format('d-m-Y') ?? '-' }}</td>
                                    <td>
                                        <div class="d-flex justify-content-center">
                                            @permission('farm-management.update')
                                                <a href="{{ route('farm-management.team.edit', $member->id) }}"
                                                    class="btn btn-sm btn-primary btn-action" title="Edit access">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @endpermission

                                            @permission('farm-management.delete')
                                                <form action="{{ route('farm-management.team.destroy', $member->id) }}"
                                                    method="POST" class="d-inline confirm-form ml-1">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="btn btn-sm btn-danger btn-action confirm-btn"
                                                        data-confirm="They are removed from the team.">
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
@endsection

@push('scripts')
    @include('admin.farm-management.partials._table-scripts', ['entity' => 'people'])
@endpush

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/bootstrap4.min.css') }}">
@endpush
