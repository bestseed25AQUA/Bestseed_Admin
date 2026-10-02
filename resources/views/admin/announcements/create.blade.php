@extends('admin.layouts.main')

@section('page_title', 'Add Announcement')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Add Announcement</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('announcements.index') }}">Announcements</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Add Announcement</h4>
                        @include('flash_msg')

                        <form class="forms-sample" method="POST" action="{{ route('announcements.store') }}"
                            enctype="multipart/form-data">
                            @csrf

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title"
                                        class="form-control @error('title') is-invalid @enderror"
                                        value="{{ old('title') }}" placeholder="Announcement title" required>
                                    @error('title')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Send To <span class="text-danger">*</span></label>
                                    <select name="audience"
                                        class="form-control @error('audience') is-invalid @enderror" required>
                                        <option value="">Select Audience</option>
                                        @foreach ($audiences as $value => $label)
                                            <option value="{{ $value }}"
                                                {{ old('audience') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('audience')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Show On</label>
                                    <select name="screen"
                                        class="form-control @error('screen') is-invalid @enderror">
                                        @foreach ($screens as $value => $label)
                                            <option value="{{ $value }}"
                                                {{ (string) old('screen', '') === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('screen')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                    {{-- Said here rather than left to be discovered: the two
                                         behave differently. A general announcement pops once and
                                         then waits in Profile; a screen one greets the farmer
                                         every time that screen opens, and the newest wins. --}}
                                    <small class="form-text text-muted">
                                        <strong>General</strong> pops up once, then stays in the
                                        app's announcements list.
                                        <strong>Farm Management</strong> pops up each time the
                                        farmer opens that screen — the newest one only.
                                    </small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea name="description" rows="5" class="form-control @error('description') is-invalid @enderror"
                                        placeholder="Announcement details shown inside the app dialog" required>{{ old('description') }}</textarea>
                                    @error('description')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Image <span class="text-muted">(optional)</span></label>
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                    <small class="text-muted">JPG, PNG or WEBP. Max 10MB.</small>
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
                                            id="is_active" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            Active — send now and show in the apps
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Submit</button>
                                <a href="{{ route('announcements.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
