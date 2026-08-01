@extends('admin.layouts.main')

@section('page_title', 'Edit Driver')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Edit Driver</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('drivers.index') }}">Drivers</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Driver</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Edit Driver</h4>
                        @include('flash_msg')

                        <form class="forms-sample" method="POST" action="{{ route('drivers.update', $driver->id) }}"
                            enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name', $driver->name) }}" placeholder="Enter driver name" required>
                                    @error('name')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mobile <span class="text-danger">*</span></label>
                                    <input type="text" name="mobile"
                                        class="form-control @error('mobile') is-invalid @enderror"
                                        value="{{ old('mobile', $driver->mobile) }}" placeholder="Enter mobile number" required>
                                    @error('mobile')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Profile Image</label>
                                    @if ($driver->profile_image)
                                        <div class="mb-2">
                                            <img src="{{ asset($driver->profile_image) }}" alt="Current Profile" style="height:80px; width:80px; border-radius:50%; object-fit:cover;">
                                            <small class="d-block text-muted mt-1">Current image</small>
                                        </div>
                                    @endif
                                    <input type="file" name="profile_image" class="form-control @error('profile_image') is-invalid @enderror" accept="image/*">
                                    <small class="text-muted">Leave empty to keep current image</small>
                                    @error('profile_image')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <div class="form-check mt-2">
                                        <input type="checkbox" name="status" value="1" class="form-check-input" id="statusCheck" {{ old('status', $driver->status) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="statusCheck">Active</label>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Update</button>
                                <a href="{{ route('drivers.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>

                        {{-- Force Logout --}}
                        <hr class="mt-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-1 text-danger">Force Logout</h6>
                                <small class="text-muted">Immediately log this driver out of the app, even if they are on an active journey.</small>
                            </div>
                            <form method="POST" action="{{ route('drivers.force-logout', $driver->id) }}"
                                  onsubmit="return confirm('Force logout {{ $driver->name }}? They will be signed out immediately on their device.')">
                                @csrf
                                <button type="submit" class="btn btn-danger">Force Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
