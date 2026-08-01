@extends('admin.layouts.main')

@section('page_title', 'Best Deals')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Best Deals</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('best-deals.index') }}">Best Deals</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Add New Best Deal</h4>
                        @include('flash_msg')

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('best-deals.store') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="row">
                                <!-- Title -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                                    @error('title')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Subtitle -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Subtitle</label>
                                    <input type="text" name="subtitle" class="form-control" value="{{ old('subtitle') }}" placeholder="e.g. Gutwell, Vibract">
                                    @error('subtitle')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Media Upload -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Upload Media (Images/Videos)</label>
                                    <input type="file" name="media_files[]" class="form-control" multiple accept="image/jpeg,image/png,image/jpg,video/mp4,video/avi">
                                    <small class="text-muted">You can select multiple images and videos. Max 50MB each.</small>
                                    @error('media_files')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                    @error('media_files.*')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Sort Order -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sort Order</label>
                                    <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
                                    @error('sort_order')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Call Number -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Call Number</label>
                                    <input type="text" name="call_number" class="form-control" value="{{ old('call_number') }}" placeholder="e.g. 9876543210">
                                    @error('call_number')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- WhatsApp Number -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">WhatsApp Number</label>
                                    <input type="text" name="whatsapp_number" class="form-control" value="{{ old('whatsapp_number') }}" placeholder="e.g. 9876543210">
                                    @error('whatsapp_number')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Description -->
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control sun-editor" rows="4">{{ old('description') }}</textarea>
                                    @error('description')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Status -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="is_active" class="form-control">
                                        <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @error('is_active')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Create</button>
                                <a href="{{ route('best-deals.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
