@extends('admin.layouts.main')

@section('page_title', 'Add Driver')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Add Driver</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('drivers.index') }}">Drivers</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add Driver</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Add New Driver</h4>
                        @include('flash_msg')

                        <form class="forms-sample" method="POST" action="{{ route('drivers.store') }}"
                            enctype="multipart/form-data">
                            @csrf

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name') }}" placeholder="Enter driver name" required>
                                    @error('name')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mobile <span class="text-danger">*</span></label>
                                    <input type="text" name="mobile"
                                        class="form-control @error('mobile') is-invalid @enderror"
                                        value="{{ old('mobile') }}" placeholder="Enter mobile number" required>
                                    @error('mobile')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Profile Image</label>
                                    <input type="file" name="profile_image" class="form-control @error('profile_image') is-invalid @enderror" accept="image/*">
                                    @error('profile_image')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <div class="form-check mt-2">
                                        <input type="checkbox" name="status" value="1" class="form-check-input" id="statusCheck" {{ old('status', 1) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="statusCheck">Active</label>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Create</button>
                                <a href="{{ route('drivers.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
