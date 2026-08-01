@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title d-flex align-items-center">
            <i class="fas fa-user-cog mr-2"></i> Admin Profile
        </h3>
        @include('flash_msg')
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Admin Profile</li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-body">
            <h4 class="card-title mb-4">Edit Profile</h4>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('danger'))
                <div class="alert alert-danger">{{ session('danger') }}</div>
            @endif

            <form method="POST" action="{{ route('update_admin_profile') }}">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label font-weight-bold">Full Name</label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name', $user->name) }}"
                               class="form-control @error('name') is-invalid @enderror"
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label font-weight-bold">Email Address</label>
                        <input type="email" id="email" name="email"
                               value="{{ old('email', $user->email) }}"
                               class="form-control @error('email') is-invalid @enderror"
                               required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <hr>
                <h5 class="mb-3 text-primary"><i class="fas fa-lock mr-1"></i> Change Password</h5>
                <p class="text-muted">Leave blank if you don’t want to change your password.</p>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="old_password" class="form-label font-weight-bold">Current Password</label>
                        <input type="password" id="old_password" name="old_password"
                               class="form-control @error('old_password') is-invalid @enderror">
                        @error('old_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="password" class="form-label font-weight-bold">New Password</label>
                        <input type="password" id="password" name="password"
                               class="form-control @error('password') is-invalid @enderror">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="password_confirmation" class="form-label font-weight-bold">Confirm Password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               class="form-control">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Update Profile
                    </button>
                    <a href="{{ route('admin') }}" class="btn btn-secondary">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card {
        border-radius: 0.75rem;
    }
    .card-title {
        font-weight: 600;
        color: #007bff;
    }
    .form-label {
        color: #333;
    }
    input.form-control {
        border-radius: 0.375rem;
    }
</style>
@endpush
