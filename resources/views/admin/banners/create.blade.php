@extends('admin.layouts.main')

@section('page_title', 'Add Banner')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Add Banner</h3>
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
                        <h4 class="card-title">Add Banner</h4>
                        @include('flash_msg')

                        <form class="forms-sample" method="POST" action="{{ route('banners.store') }}"
                            enctype="multipart/form-data">
                            @csrf

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title"
                                        class="form-control @error('title') is-invalid @enderror"
                                        value="{{ old('title') }}">
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
                                                {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                                {{ $category->category_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div> --}}



                            </div>


                            <div class="row ">
                                <div class="col-md-12">
                                    <label class="form-label">Upload Banner Image</label>
                                    <input type="file" name="image" class="filepond" data-max-file-size="5MB"
                                        accept="image/*,video/*">
                                    @error('image')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
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
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Screen</label>
                                    <select name="screen" id="screen"
                                        class="form-control @error('screen') is-invalid @enderror">
                                        <option value="">Select Screen</option>
                                        {{-- <option value="home" {{ old('screen') == 'home' ? 'selected' : '' }}>Home Screen
                                            Slider</option> --}}
                                        {{-- <option value="wanted" {{ old('screen') == 'wanted' ? 'selected' : '' }}>Wanted
                                            Screen Slider</option> --}}
                                         <option value="home_bg" {{ old('screen') == 'home_bg' ? 'selected' : '' }}>HomeScreen
                                            background </option>
                                        <option value="home_top_bg" {{ old('screen') == 'home_top_bg' ? 'selected' : '' }}>HomeScreen
                                            top background </option>
                                        <option value="home_best_deals" {{ old('screen') == 'home_best_deals' ? 'selected' : '' }}>HomeScreen
                                            best deals</option>
                                        <option value="home_banner" {{ old('screen') == 'home_banner' ? 'selected' : '' }}>Vehicle Availability
                                            Banner</option>
                                        <option value="seed_price_banner" {{ old('screen') == 'seed_price_banner' ? 'selected' : '' }}>Wanted
                                            Banner</option>
                                        <option value="spot_hatcheries_icon" {{ old('screen') == 'spot_hatcheries_icon' ? 'selected' : '' }}>Spot Hatcheries
                                            Icon</option>
                                        <option value="farm_management_icon" {{ old('screen') == 'farm_management_icon' ? 'selected' : '' }}>Farm Management
                                            Icon</option>
                                        <option value="home_section1_bg" {{ old('screen') == 'home_section1_bg' ? 'selected' : '' }}>HomeScreen
                                            Section1 Background</option>
                                        {{-- <option value="price" {{ old('screen') == 'price' ? 'selected' : '' }}>Price
                                            Screen Slider</option> --}}
                                        {{-- <option value="booking" {{ old('screen') == 'booking' ? 'selected' : '' }}>Booking
                                        </option> --}}
                                        {{-- <option value="cart" {{ old('screen') == 'cart' ? 'selected' : '' }}>Cart
                                        </option>
                                        <option value="stock" {{ old('screen') == 'stock' ? 'selected' : '' }}>Stock
                                        </option> --}}
                                          {{-- <option value="spothatchery" {{ old('screen') == 'spothatchery' ? 'selected' : '' }}>Spot hatchery
                                        </option> --}}
                                          {{-- <option value="update" {{ old('screen') == 'update' ? 'selected' : '' }}>Update Screen
                                        </option> --}}
                                          {{-- <option value="news" {{ old('screen') == 'news' ? 'selected' : '' }}>News Screen
                                        </option> --}}
                                          {{-- <option value="promo" {{ old('screen') == 'promo' ? 'selected' : '' }}>Promo Screen
                                        </option> --}}
                                        {{-- <option value="hatcherybanner"
                                            {{ old('screen') == 'hatcherybanner' ? 'selected' : '' }}>Hatchery Banner
                                        </option> --}}
                                    </select>
                                    @error('screen')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-md-6" id="hatcheryDiv" style="display: none;">
                                    <label class="form-label">Hatchery Name</label>
                                    <select name="hatchery_id" class="form-control">
                                        <option value="">Select Hatcheries</option>
                                        @foreach ($data as $hatchery)
                                            <option value="{{ $hatchery->id }}"
                                                {{ old('hatchery_id') == $hatchery->id ? 'selected' : '' }}>
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


                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Create</button>
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

    <script src="{{ asset('admin_assets/ravindra/js/jquery-3.6.0.min.js') }}"></script>
    {{-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> --}}
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const screenSelect = document.getElementById('screen');
            const hatcheryDiv = document.getElementById('hatcheryDiv');

            function toggleHatchery() {
                if (screenSelect.value === 'hatcherybanner') {
                    hatcheryDiv.style.display = 'block';
                } else {
                    hatcheryDiv.style.display = 'none';
                }
            }

            // Initial check (for edit page or old value)
            toggleHatchery();

            // Listen for changes
            screenSelect.addEventListener('change', toggleHatchery);
        });
    </script>
@endpush
