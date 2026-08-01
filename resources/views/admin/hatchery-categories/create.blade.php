@extends('admin.layouts.main')

@section('content')

    {{-- Move this to the top of your file --}}
@section('page_title', 'Hatchery Categories')
{{-- <div class="main-panel"> --}}
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            Hatchery Categories
        </h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Hatchery Categories</li>
            </ol>
        </nav>
    </div>
    <div class="row">

        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Create Hatchery Categories</h4>
                    @include('flash_msg')
                    <form class="forms-sample" method="POST" action="{{ route('hatchery-categories.store') }}"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Category Name</label>
                                <input type="text" name="category_name" class="form-control"
                                    value="{{ old('category_name') }}">
                                @error('category_name')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category Type</label>
                                <select name="type" class="form-control">
                                    <option value="">-- Select Type --</option>
                                    <option value="shrimp" {{ old('type') == 'shrimp' ? 'selected' : '' }}>Shrimp
                                    </option>
                                    <option value="fish" {{ old('type') == 'fish' ? 'selected' : '' }}>Fish</option>
                                </select>
                                @error('type')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <input type="text" name="priority" class="form-control"
                                    value="{{ old('priority') }}">
                                @error('priority')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <!-- 📝 Category Description -->
                            <div class="col-md-12 mt-3">
                                <label class="form-label">Category Description</label>
                                <textarea name="category_description" class="form-control" rows="3">{{ old('category_description') }}</textarea>
                                @error('category_description')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <!-- 📄 Category Report (PDF / Doc) -->
                            <div class="col-md-6 mt-3">
                                <label class="form-label">Category Report (PDF / Doc)</label>
                                <input type="file" name="category_report" class="form-control"
                                    accept=".pdf,.doc,.docx">
                                @error('category_report')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <!-- image upload hatchery categories acc to nabeela -->
                            <div class="mb-3no col-md-6 mt-3">
                                <label class="form-label">Upload Hatchery Category Images (PNG, JPG)</label>
                                <input type="file" name="image[]" class="filepond" multiple
                                    accept="image/png,image/jpeg,video/mp4,video/webm,video/ogg,video/*">
                                @error('image')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                            <!-- hatch cat end -->
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary me-2">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
