@extends('admin.layouts.main')

@section('page_title', 'Edit Banner')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Edit Banner</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Banners</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Update Banner</h4>
                        @include('flash_msg')

                        {{-- <form class="forms-sample" method="POST" action="{{ route('banners.update', $banner->id) }}" --}}
                            <form class="forms-sample" method="POST" action="{{ url('admin/banners/' . $banner->id) }}"
                            enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            {{-- Title (6-column width) --}}
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title"
                                        class="form-control @error('title') is-invalid @enderror"
                                        value="{{ old('title', $banner->title) }}">
                                    @error('title')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                {{-- <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" id="category_id"
                                        class="form-control @error('category_id') is-invalid @enderror">
                                        <option value="">Select Category</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                {{ old('category_id', $banner->category_id ?? '') == $category->id ? 'selected' : '' }}>
                                                {{ $category->category_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div> --}}

                            </div>

                            {{-- Image (Full 12-column width) --}}
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Upload Banner (Image/Video)</label>
                                    <input type="file" name="image" class="filepond" data-max-file-size="5MB"
                                        data-existing="{{ !empty($banner->image) ? asset($banner->image) : '' }}"
                                        accept="image/*,video/*">
                                    @error('image')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror

                                    @if (!empty($banner->image))
                                        @php
                                            $ext = strtolower(pathinfo($banner->image, PATHINFO_EXTENSION));
                                        @endphp

                                        @if (in_array($ext, ['mp4', 'mov', 'avi', 'wmv', 'webm']))
                                            <video width="320" height="240" controls>
                                                <source src="{{ asset($banner->image) }}"
                                                    type="video/{{ $ext }}">
                                                Your browser does not support the video tag.
                                            </video>
                                        @else
                                            {{-- <img src="{{ asset($banner->image) }}" alt="Banner Image" style="max-width:320px;"> --}}
                                        @endif
                                    @endif
                                </div>
                            </div>



                            {{-- Thumbnail (only for Vehicle Availability Banner) --}}
                            <div class="row mb-3" id="thumbnailDiv" style="display: none;">
                                <div class="col-md-12">
                                    <label class="form-label">Upload Thumbnail Image (shows while video loads)</label>
                                    <input type="file" name="thumbnail" class="form-control" accept="image/*">
                                    @error('thumbnail')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror

                                    @if (!empty($banner->thumbnail))
                                        <div class="mt-2">
                                            <p class="text-muted mb-1">Current Thumbnail:</p>
                                            <img src="{{ asset($banner->thumbnail) }}" alt="Thumbnail" style="max-width:320px; border-radius:8px;">
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Screen & Status (6-column each) --}}
                            <div class="row mb-3">
                                {{-- Screen Dropdown --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Screen</label>
                                    <select name="screen" id="screen"
                                        class="form-control @error('screen') is-invalid @enderror">
                                        <option value="">Select Screen</option>
                                        {{-- <option value="home"
                                            {{ old('screen', $banner->screen ?? '') == 'home' ? 'selected' : '' }}>Home
                                            Screen Slider</option> --}}
                                        {{-- <option value="wanted"
                                            {{ old('screen', $banner->screen ?? '') == 'wanted' ? 'selected' : '' }}>Wanted
                                            Screen Slider</option> --}}
                                         <option value="home_bg"
                                            {{ old('screen', $banner->screen ?? '') == 'home_bg' ? 'selected' : '' }}>HomeScreen
                                            background </option>
                                        <option value="home_top_bg"
                                            {{ old('screen', $banner->screen ?? '') == 'home_top_bg' ? 'selected' : '' }}>HomeScreen
                                            top background </option>
                                        <option value="home_best_deals"
                                            {{ old('screen', $banner->screen ?? '') == 'home_best_deals' ? 'selected' : '' }}>HomeScreen
                                            best deals </option>
                                        <option value="home_banner"
                                            {{ old('screen', $banner->screen ?? '') == 'home_banner' ? 'selected' : '' }}>Vehicle Availability
                                            Banner</option>
                                        <option value="seed_price_banner"
                                            {{ old('screen', $banner->screen ?? '') == 'seed_price_banner' ? 'selected' : '' }}>Wanted
                                            Banner</option>
                                        <option value="spot_hatcheries_icon"
                                            {{ old('screen', $banner->screen ?? '') == 'spot_hatcheries_icon' ? 'selected' : '' }}>Spot Hatcheries
                                            Icon</option>
                                        <option value="farm_management_icon"
                                            {{ old('screen', $banner->screen ?? '') == 'farm_management_icon' ? 'selected' : '' }}>Farm Management
                                            Icon</option>
                                        <option value="home_section1_bg"
                                            {{ old('screen', $banner->screen ?? '') == 'home_section1_bg' ? 'selected' : '' }}>HomeScreen
                                            Section1 Background</option>
                                        {{-- <option value="price"
                                            {{ old('screen', $banner->screen ?? '') == 'price' ? 'selected' : '' }}>Price
                                            Screen Slider</option> --}}
                                        {{-- <option value="booking"
                                            {{ old('screen', $banner->screen ?? '') == 'booking' ? 'selected' : '' }}>
                                            Booking</option>
                                        <option value="cart"
                                            {{ old('screen', $banner->screen ?? '') == 'cart' ? 'selected' : '' }}>Cart
                                        </option>
                                        <option value="stock"
                                            {{ old('screen', $banner->screen ?? '') == 'stock' ? 'selected' : '' }}>Stock
                                        </option> --}}
                                        {{-- <option value="hatcherybanner"
                                            {{ old('screen', $banner->screen ?? '') == 'hatcherybanner' ? 'selected' : '' }}>
                                            Hatchery Banner</option> --}}
                                        {{-- <option value="update"
                                            {{ old('screen', $banner->screen ?? '') == 'update' ? 'selected' : '' }}>
                                            Update Screen
                                        </option> --}}
                                        {{-- <option value="news"
                                            {{ old('screen', $banner->screen ?? '') == 'news' ? 'selected' : '' }}>
                                            News Screen
                                        </option> --}}
                                        {{-- <option value="promo"
                                            {{ old('screen', $banner->screen ?? '') == 'promo' ? 'selected' : '' }}>
                                            Promo Screen
                                        </option> --}}

                                    </select>
                                    @error('screen')
                                        <span class="invalid-feedback d-block">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                {{-- Hatchery Dropdown (hidden by default) --}}
                                <div class="col-md-6" id="hatcheryDiv" style="display: none;">
                                    <label class="form-label">Hatchery Name</label>
                                    <select name="hatchery_id"
                                        class="form-control @error('hatchery_id') is-invalid @enderror">
                                        <option value="">Select Hatcheries</option>
                                        @foreach ($data as $hatchery)
                                            <option value="{{ $hatchery->id }}"
                                                {{ old('hatchery_id', $banner->hatchery_id ?? '') == $hatchery->id ? 'selected' : '' }}>
                                                {{ $hatchery->picker_label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('hatchery_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Script --}}
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const screenSelect = document.getElementById('screen');
                                    const hatcheryDiv = document.getElementById('hatcheryDiv');
                                    const thumbnailDiv = document.getElementById('thumbnailDiv');

                                    function toggleFields() {
                                        hatcheryDiv.style.display = screenSelect.value === 'hatcherybanner' ? 'block' : 'none';
                                        thumbnailDiv.style.display = screenSelect.value === 'home_banner' ? 'block' : 'none';
                                    }

                                    toggleFields();
                                    screenSelect.addEventListener('change', toggleFields);
                                });
                            </script>



                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control @error('status') is-invalid @enderror">
                                    <option value="1"
                                        {{ old('status', $banner->status ?? 1) == 1 ? 'selected' : '' }}>Active
                                    </option>
                                    <option value="0"
                                        {{ old('status', $banner->status ?? 1) == 0 ? 'selected' : '' }}>Inactive
                                    </option>
                                </select>
                                @error('status')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>


                            {{-- Buttons --}}
                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Update</button>
                                <a href="{{ route('banners.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>


                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <!-- Select2 CSS -->



    <link href="{{ asset('admin_assets/ravindra/css/select2.min.css') }}" rel="stylesheet" />

    {{-- <script src="{{ asset('admin_assets/ravindra/js/jquery-3.6.0.min.js') }}"></script> --}}
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="{{ asset('admin_assets/ravindra/js/select2.min.js') }}"></script>

    {{-- <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /> --}}

    <!-- jQuery -->
    {{-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> --}}

    <!-- Select2 JS -->
    {{-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> --}}

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: "Select categories",
                allowClear: true
            });
        });
    </script>
@endpush
