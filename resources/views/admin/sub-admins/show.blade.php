@extends('admin.layouts.main')

@section('page_title', 'View Sub Admin')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">View Sub Admin</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.sub-admins.index') }}">Sub Admins</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $sub_admin->name }}</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <div style="width:100px; height:100px; border-radius:50%; background:#e9ecef; display:flex; align-items:center; justify-content:center; margin:0 auto;">
                                <i class="fas fa-user fa-3x text-muted"></i>
                            </div>
                        </div>
                        <h4>{{ $sub_admin->name }}</h4>
                        <p class="text-muted">{{ $sub_admin->email }}</p>

                        @if($sub_admin->is_super_admin)
                            <span class="badge badge-danger badge-lg">Super Admin</span>
                        @endif

                        <hr>

                        <div class="text-left">
                            <p><strong>Status:</strong>
                                @if($sub_admin->is_admin)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </p>
                            <p><strong>Created:</strong> {{ $sub_admin->created_at->format('d M Y, H:i') }}</p>
                            <p><strong>Last Updated:</strong> {{ $sub_admin->updated_at->format('d M Y, H:i') }}</p>
                        </div>

                        <div class="mt-4">
                            @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('sub-admins.update'))
                                @if(!$sub_admin->is_super_admin || auth()->user()->isSuperAdmin())
                                    <a href="{{ route('admin.sub-admins.edit', $sub_admin->id) }}" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                @endif
                            @endif
                            <a href="{{ route('admin.sub-admins.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Assigned Roles</h5>
                        @forelse($sub_admin->roles as $role)
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                <div>
                                    <strong>{{ $role->name }}</strong>
                                    <br><small class="text-muted">{{ $role->permissions->count() }} permissions</small>
                                </div>
                                <a href="{{ route('admin.roles.show', $role->id) }}" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        @empty
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                No roles assigned
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            All Permissions
                            <span class="badge badge-primary">{{ $allPermissions->count() }} total</span>
                        </h4>

                        @if($sub_admin->is_super_admin)
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Super Admin</strong> has access to all permissions in the system.
                            </div>
                        @endif

                        @php
                            $groupedPermissions = $allPermissions->groupBy('module');
                        @endphp

                        @if($groupedPermissions->count() > 0)
                            <div class="row mt-3">
                                @foreach($groupedPermissions as $module => $modulePermissions)
                                    <div class="col-md-6 mb-3">
                                        <div class="card border">
                                            <div class="card-header bg-light py-2">
                                                <strong>{{ ucwords(str_replace('-', ' ', $module)) }}</strong>
                                                <span class="badge badge-primary float-right">{{ $modulePermissions->count() }}</span>
                                            </div>
                                            <div class="card-body py-2">
                                                @foreach($modulePermissions as $permission)
                                                    <span class="badge badge-outline-success mb-1">
                                                        <i class="fas fa-check mr-1"></i>
                                                        {{ ucfirst(str_replace($module . '.', '', $permission->slug)) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                This user has no permissions. Assign a role to grant permissions.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .badge-outline-success {
            color: #28a745;
            background-color: transparent;
            border: 1px solid #28a745;
        }
        .badge-lg {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
    </style>
@endpush
