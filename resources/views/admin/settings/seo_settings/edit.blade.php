@extends('admin.layouts.main')

@section('page_title', 'Edit SEO')

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
                        <li class="breadcrumb-item active text-primary fw-semibold">Edit SEO</li>
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
                <h3 class="card-title mb-0">Edit SEO</h3>
            </div>
            <div class="card-body">
                @include('flash_msg')

                <form action="{{ route('seo-settings.update', $data->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- Page Name -->
                    <div class="mb-3">
                        <label for="page_name">Page Name *</label>
                        <input 
                            type="text" 
                            class="form-control @error('page_name') is-invalid @enderror"
                            name="page_name" 
                            id="page_name" 
                            value="{{ old('page_name', $data->page_name) }}"
                            autocomplete="off"
                        >
                        @error('page_name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <!-- Title -->
                    <div class="mb-3">
                        <label for="title">Title *</label>
                        <input 
                            type="text" 
                            class="form-control @error('title') is-invalid @enderror"
                            name="title" 
                            id="title" 
                            value="{{ old('title', $data->title) }}"
                            autocomplete="off"
                        >
                        @error('title')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <!-- Keywords -->
                    <div class="mb-3">
                        <label for="keywords">Keywords *</label>
                        <input 
                            type="text" 
                            class="form-control @error('keywords') is-invalid @enderror"
                            name="keywords" 
                            id="keywords" 
                            value="{{ old('keywords', $data->keywords) }}"
                            autocomplete="off"
                        >
                        @error('keywords')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                        <small class="text-muted">Press <kbd>Enter</kbd> after each keyword</small>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label for="description">Description *</label>
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
                    </div>

                    <!-- Submit Button -->
                    <div class="d-flex justify-content-end">
                        <button type="submit" id="submit" class="btn btn-primary px-4">
                            Update SEO
                        </button>
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
