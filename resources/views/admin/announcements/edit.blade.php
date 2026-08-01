@extends('admin.layouts.main')

@section('page_title', 'Edit Announcement')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Edit Announcement</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('announcements.index') }}">Announcements</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Edit Announcement</h4>
                        @include('flash_msg')

                        <form class="forms-sample" method="POST"
                            action="{{ route('announcements.update', $announcement->id) }}" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title"
                                        class="form-control @error('title') is-invalid @enderror"
                                        value="{{ old('title', $announcement->title) }}" required>
                                    @error('title')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Send To <span class="text-danger">*</span></label>
                                    <select name="audience"
                                        class="form-control @error('audience') is-invalid @enderror" required>
                                        @foreach ($audiences as $value => $label)
                                            <option value="{{ $value }}"
                                                {{ old('audience', $announcement->audience) == $value ? 'selected' : '' }}>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('audience')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea name="description" rows="5" class="form-control @error('description') is-invalid @enderror" required>{{ old('description', $announcement->description) }}</textarea>
                                    @error('description')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Image <span class="text-muted">(optional)</span></label>
                                    @if ($announcement->image)
                                        <div class="mb-2">
                                            <a href="{{ asset($announcement->image) }}" target="_blank">
                                                <img src="{{ asset($announcement->image) }}" alt="Announcement"
                                                    style="height:70px;">
                                            </a>
                                        </div>
                                    @endif
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                    <small class="text-muted">Leave empty to keep the current image.</small>
                                    @error('image')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                            id="is_active"
                                            {{ old('is_active', $announcement->is_active ? '1' : '0') == '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            Active — visible in the apps
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Update</button>
                                <a href="{{ route('announcements.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
