@extends('admin.layouts.main')

@section('page_title', 'Create Sub Admin')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Create Sub Admin</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.sub-admins.index') }}">Sub Admins</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create Sub Admin</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Create New Sub Admin</h4>
                        @include('flash_msg')

                        <form class="forms-sample" method="POST" action="{{ route('admin.sub-admins.store') }}">
                            @csrf

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name') }}" placeholder="Enter full name" required>
                                    @error('name')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        value="{{ old('email') }}" placeholder="Enter email address" required>
                                    @error('email')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        placeholder="Enter password" required>
                                    @error('password')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password_confirmation"
                                        class="form-control"
                                        placeholder="Confirm password" required>
                                </div>
                            </div>

                            @if(auth()->user()->isSuperAdmin())
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check mt-2">
                                            <input type="checkbox" name="is_super_admin" value="1" class="form-check-input" id="isSuperAdmin" {{ old('is_super_admin') ? 'checked' : '' }}>
                                            <label class="form-check-label text-danger" for="isSuperAdmin">
                                                <strong>Make Super Admin</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted">Super Admin has full access to all features and can manage other admins</small>
                                    </div>
                                </div>
                            @endif

                            <hr class="my-4">

                            <h5 class="mb-3">Assign Roles <span class="text-danger">*</span></h5>
                            @error('roles')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror

                            <div class="row">
                                @foreach($roles as $role)
                                    <div class="col-md-4 mb-3">
                                        <div class="card border {{ in_array($role->id, old('roles', [])) ? 'border-primary' : '' }}">
                                            <div class="card-body py-3">
                                                <div class="form-check">
                                                    <input type="checkbox" name="roles[]"
                                                        value="{{ $role->id }}"
                                                        class="form-check-input role-checkbox"
                                                        id="role_{{ $role->id }}"
                                                        {{ in_array($role->id, old('roles', [])) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="role_{{ $role->id }}">
                                                        <strong>{{ $role->name }}</strong>
                                                    </label>
                                                </div>
                                                @if($role->description)
                                                    <small class="text-muted d-block mt-1">{{ $role->description }}</small>
                                                @endif
                                                <small class="text-info d-block mt-1">
                                                    <i class="fas fa-key"></i> {{ $role->permissions->count() }} permissions
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Create Sub Admin</button>
                                <a href="{{ route('admin.sub-admins.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Highlight selected role cards
            $('.role-checkbox').change(function() {
                const card = $(this).closest('.card');
                if ($(this).prop('checked')) {
                    card.addClass('border-primary');
                } else {
                    card.removeClass('border-primary');
                }
            });

            // If super admin is checked, disable role selection
            $('#isSuperAdmin').change(function() {
                if ($(this).prop('checked')) {
                    $('.role-checkbox').prop('disabled', true).prop('checked', false);
                    $('.role-checkbox').closest('.card').addClass('opacity-50');
                } else {
                    $('.role-checkbox').prop('disabled', false);
                    $('.role-checkbox').closest('.card').removeClass('opacity-50');
                }
            });
        });
    </script>
@endpush

@push('styles')
    <style>
        .opacity-50 {
            opacity: 0.5;
        }
    </style>
@endpush
