@extends('admin.layouts.main')

@section('page_title', 'Edit Hatchery Post')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Edit Hatchery Post</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('hatchery-updates.index') }}">Hatchery Posts</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Update Post</h4>
                        @include('flash_msg')

                        <form method="POST" action="{{ route('hatchery-updates.update', $post->id) }}"
                            enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="row">
                                <!-- Hatchery -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Hatchery</label>
                                    <select name="hatchery_id" id="hatchery_id" class="form-control">
                                        <option value="">Select Hatchery</option>
                                        @foreach ($hatcheries as $hatchery)
                                            <option value="{{ $hatchery->id }}"
                                                data-logo="{{ $hatchery->logo ? asset($hatchery->logo) : '' }}"
                                                {{ old('hatchery_id', $post->hatchery_id) == $hatchery->id ? 'selected' : '' }}>
                                                {{ $hatchery->picker_label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('hatchery_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                    <div id="hatchery_logo_container" style="display: none; margin-top: 10px;">
                                        <small class="text-muted d-block mb-1">Hatchery Logo</small>
                                        <img id="hatchery_logo_preview" src="" class="img-fluid border rounded" style="max-height: 80px; object-fit: contain;">
                                    </div>
                                </div>

                                <!-- Vendor Select -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Vendor</label>
                                    <select name="vendor_id" id="vendor_id" class="form-control">
                                        <option value="">Select Vendor</option>
                                        @foreach ($vendors as $vendor)
                                            <option value="{{ $vendor->id }}"
                                                {{ old('vendor_id', $post->vendor_id) == $vendor->id ? 'selected' : '' }}>
                                                {{ $vendor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('vendor_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Title -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title" class="form-control"
                                        value="{{ old('title', $post->title) }}" required>
                                    @error('title')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" id="category_id" class="form-control">
                                        <option value="">Select Category</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                {{ $post->category_id == $category->id ? 'selected' : '' }}>
                                                {{ $category->category_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                {{-- Location removed — auto-set from hatchery --}}
                                <input type="hidden" name="location_id" id="location_id" value="{{ $post->location_id }}">

                                <!-- Hashtags -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Hashtags (comma separated)</label>
                                    <input type="text" name="hashtags" class="form-control"
                                        value="{{ old('hashtags', is_array($post->hashtags) ? implode(' ', $post->hashtags) : $post->hashtags) }}">
                                    @error('hashtags')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Current Thumbnail -->
                                @if ($post->image)
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Current Thumbnail</label>
                                        <div>
                                            <img src="{{ asset($post->image) }}" class="img-fluid"
                                                style="max-height: 100px; object-fit: cover; border-radius: 8px;">
                                        </div>
                                    </div>
                                @endif

                                <!-- Thumbnail Upload -->
                                <div class="col-md-6 mb-3">
                                    <label
                                        class="form-label">{{ $post->image ? 'Change Thumbnail' : 'Thumbnail Image' }}</label>
                                    <input type="file" name="thumbnail" id="thumbnail" class="form-control"
                                        accept="image/jpeg,image/png,image/jpg">
                                    <small class="text-muted">Upload a new thumbnail image for this update.</small>
                                    @error('thumbnail')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Existing Media Files -->
                                @php
                                    $existingFiles = $post->media_files ?? [];
                                    $existingTypes = $post->media_types ?? [];
                                @endphp
                                @if (!empty($existingFiles) && count($existingFiles) > 0)
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Existing Media (check to remove)</label>
                                        <div class="row">
                                            @foreach ($existingFiles as $index => $filePath)
                                                <div class="col-md-3 mb-3">
                                                    <div class="card">
                                                        <div class="card-body p-2 text-center">
                                                            @if (isset($existingTypes[$index]) && $existingTypes[$index] === 'video')
                                                                <video width="100%" height="100" controls>
                                                                    <source src="{{ asset($filePath) }}" type="video/mp4">
                                                                </video>
                                                            @else
                                                                <img src="{{ asset($filePath) }}" class="img-fluid"
                                                                    style="max-height: 100px; object-fit: cover;">
                                                            @endif
                                                            <div class="mt-2">
                                                                <label class="text-danger">
                                                                    <input type="checkbox" name="remove_media[]"
                                                                        value="{{ $filePath }}">
                                                                    Remove
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Add New Media Files -->
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Add More Media (Images & Videos)</label>
                                    <input type="file" name="media_files[]" id="media_files" class="form-control"
                                        multiple accept="image/jpeg,image/png,image/jpg,video/mp4,video/avi">
                                    <small class="text-muted">You can select multiple files. Hold Ctrl/Cmd to select
                                        multiple.</small>
                                    @error('media_files')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                    @error('media_files.*')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Description -->
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control sun-editor" rows="4">{{ old('description', $post->description) }}</textarea>
                                    @error('description')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Status -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="is_active" class="form-control">
                                        <option value="1" {{ old('is_active', $post->is_active) ? 'selected' : '' }}>
                                            Active</option>
                                        <option value="0"
                                            {{ !old('is_active', $post->is_active) ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @error('is_active')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Update</button>
                                <a href="{{ route('hatchery-updates.index') }}" class="btn btn-light">Cancel</a>
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
        // Hatchery data map for auto-selecting vendor, category, location
        @php
            $hatcheryMap = $hatcheries->keyBy('id')->map(function($h) {
                return [
                    'vendor_id' => $h->vendor_id,
                    'category_id' => $h->category_id,
                    'location_id' => $h->location_id,
                ];
            });
        @endphp
        const hatcheryMap = @json($hatcheryMap);

        function updateHatcheryLogo() {
            const select = document.getElementById('hatchery_id');
            const container = document.getElementById('hatchery_logo_container');
            const preview = document.getElementById('hatchery_logo_preview');
            
            if (select && select.value) {
                const selectedOption = select.options[select.selectedIndex];
                const logoUrl = selectedOption.getAttribute('data-logo');
                if (logoUrl) {
                    preview.src = logoUrl;
                    container.style.display = 'block';
                    return;
                }
            }
            if (container) {
                container.style.display = 'none';
                preview.src = '';
            }
        }

        document.getElementById('hatchery_id')?.addEventListener('change', function() {
            const data = hatcheryMap[this.value];
            if (data) {
                document.getElementById('vendor_id').value = data.vendor_id || '';
                document.getElementById('category_id').value = data.category_id || '';
                document.getElementById('location_id').value = data.location_id || '';
            } else {
                document.getElementById('vendor_id').value = '';
                document.getElementById('category_id').value = '';
                document.getElementById('location_id').value = '';
            }
            updateHatcheryLogo();
        });

        // Trigger on load for pre-selected value
        updateHatcheryLogo();

        // Multiple file upload info
        document.getElementById('media_files')?.addEventListener('change', function(e) {
            const files = e.target.files;
            console.log('Selected files:', files.length);
        });
    </script>
@endpush
