@extends('admin.layouts.main')

@section('page_title', 'View Role')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">View Role</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">Roles</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $role->name }}</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Role Details</h4>
                        <div class="mt-3">
                            <p><strong>Name:</strong> {{ $role->name }}</p>
                            <p><strong>Slug:</strong> <code>{{ $role->slug }}</code></p>
                            <p><strong>Description:</strong> {{ $role->description ?? 'No description' }}</p>
                            <p><strong>Default Role:</strong>
                                @if($role->is_default)
                                    <span class="badge badge-success">Yes</span>
                                @else
                                    <span class="badge badge-secondary">No</span>
                                @endif
                            </p>
                            <p><strong>Total Users:</strong> <span class="badge badge-info">{{ $role->users->count() }}</span></p>
                            <p><strong>Created:</strong> {{ $role->created_at->format('d M Y, H:i') }}</p>
                        </div>

                        <div class="mt-4">
                            @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('roles.update'))
                                <a href="{{ route('admin.roles.edit', $role->id) }}" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Edit Role
                                </a>
                            @endif
                            <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>

                @if($role->users->count() > 0)
                    <div class="card mt-4">
                        <div class="card-body">
                            <h4 class="card-title">Users with this Role</h4>
                            <ul class="list-group list-group-flush">
                                @foreach($role->users as $user)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ $user->name }}</strong>
                                            <br><small class="text-muted">{{ $user->email }}</small>
                                        </div>
                                        @if($user->is_super_admin)
                                            <span class="badge badge-danger">Super Admin</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Permissions ({{ $role->permissions->count() }})</h4>

                        @php
                            $groupedPermissions = $role->permissions->groupBy('module');
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
                                This role has no permissions assigned.
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
    </style>
@endpush
