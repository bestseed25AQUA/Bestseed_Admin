@extends('admin.layouts.main')

@section('page_title', 'Edit Social Media Settings')

@section('content')

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col"></div>
            <div class="col-auto ms-auto">
                <div class="breadcrumb-wrapper bg-white shadow-sm px-4 py-2 border">
                    <ol class="breadcrumb mb-0 rounded-end justify-content-end">
                        <li class="breadcrumb-item">
                            <a href="{{ url('/admin') }}" class="text-decoration-none text-dark fw-bold">
                                <i class="fas fa-home me-2 text-primary"></i>Dashboard
                            </a>
                        </li>
                        <li class="breadcrumb-item active text-primary fw-semibold">Edit Social Media Settings</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Edit Social Media Settings</h3>
            </div>
            <div class="card-body">
                @include('flash_msg')

               <form action="{{ route('social-media-settings.update', $data->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <!-- Page Name -->


    <!-- Facebook -->
    <div class="mb-3">
        <label for="facebook">Facebook Link</label>
        <input 
            type="text" 
            class="form-control @error('facebook') is-invalid @enderror"
            name="facebook" 
            id="facebook" 
            value="{{ old('facebook', $data->facebook) }}"
            autocomplete="off"
        >
        @error('facebook')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
        @enderror
    </div>

    <!-- Instagram -->
    <div class="mb-3">
        <label for="instagram">Instagram Link</label>
        <input 
            type="text" 
            class="form-control @error('instagram') is-invalid @enderror"
            name="instagram" 
            id="instagram" 
            value="{{ old('instagram', $data->instagram) }}"
            autocomplete="off"
        >
        @error('instagram')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
        @enderror
    </div>

    <!-- Twitter -->
    <div class="mb-3">
        <label for="twitter">Twitter Link</label>
        <input 
            type="text" 
            class="form-control @error('twitter') is-invalid @enderror"
            name="twitter" 
            id="twitter" 
            value="{{ old('twitter', $data->twitter) }}"
            autocomplete="off"
        >
        @error('twitter')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
        @enderror
    </div>

    <!-- Pinterest -->
    <div class="mb-3">
        <label for="pinterest">Pinterest Link</label>
        <input 
            type="text" 
            class="form-control @error('pinterest') is-invalid @enderror"
            name="pinterest" 
            id="pinterest" 
            value="{{ old('pinterest', $data->pinterest) }}"
            autocomplete="off"
        >
        @error('pinterest')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
        @enderror
    </div>

    <!-- Optional: Description field if needed -->
    {{-- <div class="mb-3">
        <label for="description">Meta Description</label>
        <textarea 
            class="form-control sun-editor @error('description') is-invalid @enderror"
            name="description" 
            id="description"
            autocomplete="off"
        >{{ old('description', $data->description) }}</textarea>
        @error('description')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
        @enderror
    </div> --}}

    <!-- Submit Button -->
    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-success px-4">Update Social Media</button>
    </div>
</form>

            </div>
        </div>
    </div>
</div>

@endsection

@section('csscodes')
    <link rel="stylesheet" href="{{ asset('admin_assets/dropify/dropify.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css">
@endsection

@section('jscodes')
    <script src="{{ asset('admin_assets/datatables/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('admin_assets/dropify/dropify.min.js') }}"></script>
    <script src="{{ asset('admin_assets/dist/js/form-fileuploads.init.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
    <script src="{{ asset('admin_assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('admin_assets/libs/bootstrap-select/bootstrap-select.min.js') }}"></script>
    <script src="{{ asset('admin_assets/js/app.min.js') }}"></script>

    <!-- Tagify Init -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var input = document.querySelector('#keywords');
            new Tagify(input, {
                delimiters: ",|\\n|\\r|\\t| ",
                dropdown: {
                    enabled: 0
                }
            });
        });
    </script>

    <style>
        input.form-control {
            border-radius: 0 !important;
            border: 1px solid #ced4da;
            box-shadow: none;
        }
        input.form-control:focus {
            border-color: #0d6efd;
            box-shadow: none;
        }
        label.form-label {
            font-weight: 500;
        }
        .form-section-title {
            font-size: 1.1rem;
            color: #495057;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
    </style>
@endsection
