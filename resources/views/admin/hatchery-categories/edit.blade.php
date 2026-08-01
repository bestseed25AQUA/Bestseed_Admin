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

      <form class="forms-sample" method="POST" action="{{ route('hatchery-categories.update', $data->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        {{-- <p class="card-description mt-4">User Info</p> --}}

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Category Name</label>
                <input type="text" name="category_name" class="form-control" value="{{ old('category_name',$data->category_name ) }}">
                @error('category_name')
                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
            <div class="col-md-6">
<label class="form-label">Category Type</label>
<select name="type" class="form-control">
<option value="">-- Select Type --</option>
<option value="shrimp" {{ old('type', $data->type) == 'shrimp' ? 'selected' : '' }}>
Shrimp
</option>
<option value="fish" {{ old('type', $data->type) == 'fish' ? 'selected' : '' }}>
Fish
</option>
</select>
@error('type')
<span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
@enderror
</div>
            <div class="col-md-6">
                <label class="form-label">Priority</label>
                <input type="text" name="priority" class="form-control" value="{{ old('priority',$data->priority ) }}">
                @error('priority')
                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                @enderror
            </div>

            <!-- 📝 Category Description -->
            <div class="col-md-12 mt-3">
                <label class="form-label">Category Description</label>
                <textarea name="category_description" class="form-control" rows="3">{{ old('category_description', $data->category_description) }}</textarea>
                @error('category_description')
                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                @enderror
            </div>

            <!-- 📄 Category Report -->
            <div class="col-md-6 mt-3">
                <label class="form-label">Category Report (PDF / Doc)</label>
                <input type="file" name="category_report" class="form-control" accept=".pdf,.doc,.docx">
                @error('category_report')
                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                @enderror

                @if(!empty($data->category_report))
                    <div class="mt-2">
                        <a href="{{ asset($data->category_report) }}" target="_blank" class="btn btn-sm btn-outline-info">
                            View Existing Report
                        </a>
                    </div>
                @endif
            </div>

            {{-- Hatchery Images --}}
            <div class="mb-3no col-md-6 mt-3">
                <label class="form-label">Upload Hatchery Category Images (PNG, JPG)</label>
                <input type="file" name="image[]" class="filepond" multiple
                       accept="image/png,image/jpeg,video/mp4,video/webm,video/ogg,video/*">
                @error('image')
                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                @enderror

                @php
                    // Normalize image data to always be an array
                    $images = [];
                    if (!empty($data->image)) {
                        if (is_string($data->image)) {
                            $decoded = json_decode($data->image, true);
                            $images = is_array($decoded) ? $decoded : [$data->image];
                        } elseif (is_array(value: $data->image)) {
                            $images = $data->image;
                        }
                    }
                @endphp

                @if (!empty($images))
                    <div class="mt-2 d-flex flex-wrap" id="existing-images">
                        @foreach ($images as $index => $img)
                            @php
                                $extension = strtolower(pathinfo($img, PATHINFO_EXTENSION));
                                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                                $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
                            @endphp

                            <div class="position-relative mr-2 mb-2 image-container" data-index="{{ $index }}">
                                @if (in_array($extension, $imageExtensions))
                                    {{-- Show image with click to enlarge --}}
                                    <img src="{{ asset($img) }}"
                                         alt="File"
                                         width="100"
                                         class="rounded edit-image-thumb"
                                         style="cursor: pointer;"
                                         data-index="{{ $index }}"
                                         data-images='@json($images)'
                                         title="Click to view">
                                    {{-- Remove button --}}
                                    <button type="button" class="btn btn-danger btn-sm remove-image-btn"
                                            style="position: absolute; top: -8px; right: -8px; border-radius: 50%; width: 24px; height: 24px; padding: 0; line-height: 1;"
                                            data-category-id="{{ $data->id }}"
                                            data-image-path="{{ $img }}"
                                            title="Remove image">
                                        <i class="fas fa-times" style="font-size: 12px;"></i>
                                    </button>
                                @elseif (in_array($extension, $videoExtensions))
                                    {{-- Show video --}}
                                    <video width="150" height="100" class="rounded" controls>
                                        <source src="{{ asset($img) }}" type="video/{{ $extension }}">
                                        Your browser does not support the video tag.
                                    </video>
                                    {{-- Remove button for video --}}
                                    <button type="button" class="btn btn-danger btn-sm remove-image-btn"
                                            style="position: absolute; top: -8px; right: -8px; border-radius: 50%; width: 24px; height: 24px; padding: 0; line-height: 1;"
                                            data-category-id="{{ $data->id }}"
                                            data-image-path="{{ $img }}"
                                            title="Remove video">
                                        <i class="fas fa-times" style="font-size: 12px;"></i>
                                    </button>
                                @else
                                    {{-- Optional: show placeholder or skip --}}
                                    <p class="text-muted">Unsupported file type</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="text-end mt-4">
          <button type="submit" class="btn btn-primary me-2">update</button>
          {{-- <a href="{{ route('admin') }}" class="btn btn-light">Cancel</a> --}}
        </div>
      </form>
    </div>
  </div>
</div>

          </div>
        </div>

<!-- Image Modal with Carousel -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Image Preview</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div id="imageCarousel" class="carousel slide" data-ride="false">
                    <ol class="carousel-indicators" id="carouselIndicators"></ol>
                    <div class="carousel-inner" id="carouselInner"></div>
                    <a class="carousel-control-prev" href="#imageCarousel" role="button" data-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="sr-only">Previous</span>
                    </a>
                    <a class="carousel-control-next" href="#imageCarousel" role="button" data-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="sr-only">Next</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Image container */
    .image-container {
        display: inline-block;
    }

    /* Image thumbnail hover effect */
    .edit-image-thumb {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .edit-image-thumb:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    /* Modal styling */
    #imageModal .modal-header {
        border-bottom: 1px solid #dee2e6;
    }
    #imageModal .close {
        font-size: 1.5rem;
        opacity: 0.7;
    }
    #imageModal .close:hover {
        opacity: 1;
    }
    #imageModal .carousel-control-prev,
    #imageModal .carousel-control-next {
        width: 10%;
        background: rgba(0,0,0,0.3);
    }
    #imageModal .carousel-indicators li {
        background-color: #007bff;
    }
    #imageModal .modal-body {
        background: #f8f9fa;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Image click to open modal with carousel (Bootstrap 4)
    $(document).on('click', '.edit-image-thumb', function() {
        var images = $(this).data('images');
        var startIndex = $(this).data('index');

        // Clear previous content
        $('#carouselIndicators').empty();
        $('#carouselInner').empty();

        // Add images to carousel
        if (images && images.length > 0) {
            images.forEach(function(img, index) {
                var activeClass = index === startIndex ? 'active' : '';

                // Add indicator (Bootstrap 4 uses <li> elements)
                $('#carouselIndicators').append(
                    '<li data-target="#imageCarousel" data-slide-to="' + index + '" class="' + activeClass + '"></li>'
                );

                // Add slide
                var imgUrl = '{{ asset("") }}' + img;
                $('#carouselInner').append(
                    '<div class="carousel-item ' + activeClass + '">' +
                        '<img src="' + imgUrl + '" class="d-block w-100" style="max-height: 500px; object-fit: contain; background: #f8f9fa;">' +
                    '</div>'
                );
            });

            // Show/hide navigation if only one image
            if (images.length === 1) {
                $('.carousel-control-prev, .carousel-control-next, .carousel-indicators').hide();
            } else {
                $('.carousel-control-prev, .carousel-control-next, .carousel-indicators').show();
            }
        }

        // Show modal (Bootstrap 4 syntax)
        $('#imageModal').modal('show');
    });

    // Remove image button click
    $(document).on('click', '.remove-image-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var btn = $(this);
        var categoryId = btn.data('category-id');
        var imagePath = btn.data('image-path');
        var container = btn.closest('.image-container');

        Swal.fire({
            title: 'Remove Image?',
            text: "This image will be permanently removed.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Send AJAX request to remove image
                $.ajax({
                    url: '{{ route("hatchery-categories.remove-image") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        category_id: categoryId,
                        image_path: imagePath
                    },
                    success: function(response) {
                        if (response.success) {
                            // Remove the image container from DOM
                            container.fadeOut(300, function() {
                                $(this).remove();
                                // Update data-images attribute on remaining images
                                updateImageData(response.remaining_images);
                            });
                            Swal.fire({
                                icon: 'success',
                                title: 'Removed!',
                                text: 'Image has been removed.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Failed to remove image.', 'error');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error', 'Failed to remove image. Please try again.', 'error');
                    }
                });
            }
        });
    });

    // Update image data attributes after removal
    function updateImageData(remainingImages) {
        $('.edit-image-thumb').each(function(index) {
            $(this).data('images', remainingImages);
            $(this).attr('data-images', JSON.stringify(remainingImages));
            $(this).data('index', index);
            $(this).attr('data-index', index);
        });
    }
});
</script>
@endpush
