@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-dna mr-2"></i></i>Hatchery Categories
            </h3>
            @include('flash_msg')
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Hatchery Categories </li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">Hatchery Categories</h4>
                    <div class="btn-group" role="group">
                        <a href="{{ route('hatchery-categories.index', ['type' => 'shrimp']) }}"
                            class="btn {{ ($type ?? 'shrimp') === 'shrimp' ? 'btn-primary' : 'btn-outline-primary' }}">
                            🦐 Shrimp Category
                        </a>


                        <a href="{{ route('hatchery-categories.index', ['type' => 'fish']) }}"
                            class="btn {{ ($type ?? 'shrimp') === 'fish' ? 'btn-primary' : 'btn-outline-primary' }}">
                            🐟 Fish Category
                        </a>
                    </div>
                    @permission('hatchery-categories.create')
                    <a href="{{ route('hatchery-categories.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Add Hatchery Categories
                    </a>
                    @endpermission
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Hatchery Category Name</th>
                                <th>Priority</th>
                                <th>Description</th>
                                <th>Report</th>
                                <th>Image</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $item)
                                <tr>
                                    <td class="font-weight-bold text-primary">#{{ $item->id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm mr-3">
                                                <span
                                                    class="avatar-title bg-light rounded-circle text-primary d-flex align-items-center justify-content-center"
                                                    style="width: 40px; height: 40px;">
                                                    {{ strtoupper(substr($item->category_name, 0, 1)) }}
                                                </span>
                                            </div>
                                            <div>
                                                <div class="font-weight-medium">{{ $item->category_name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-phone-alt text-muted mr-2"></i>
                                            {{ $item->priority ?? 'N/A' }}
                                        </div>
                                    </td>

                                    {{-- 📝 Description --}}
                                    <td>
                                        {{ \Illuminate\Support\Str::limit($item->category_description, 60) ?? 'N/A' }}
                                    </td>

                                    {{-- 📄 Report --}}
                                    <td>
                                        @if (!empty($item->category_report))
                                            <a href="{{ asset($item->category_report) }}" target="_blank"
                                                class="btn btn-sm btn-outline-info">
                                                View Report
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>

                                    {{-- 🖼 Image (first one) --}}
                                    <td>
                                        @php
                                            $images = $item->image ? json_decode($item->image, true) : [];
                                        @endphp

                                        @if (!empty($images) && isset($images[0]))
                                            <img src="{{ asset($images[0]) }}" alt="Category Image" width="60"
                                                class="rounded category-image-thumb" style="cursor: pointer;"
                                                data-images='@json($images)'
                                                data-category="{{ $item->category_name }}">
                                        @else
                                            -
                                        @endif
                                    </td>


                                    <td>
                                        <div class="d-flex">

                                            @permission('hatchery-categories.update')
                                            <a href="{{ route('hatchery-categories.edit', $item->id) }}"
                                                class="btn btn-sm btn-primary btn-action ml-1" title="Edit Vendor">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endpermission
                                            @permission('hatchery-categories.delete')
                                            <form action="{{ route('hatchery-categories.destroy', $item->id) }}"
                                                method="POST" class="d-inline delete-form ml-1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button"
                                                    class="btn btn-sm btn-danger btn-action delete-vendor-btn"
                                                    title="Delete Vendor">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            @endpermission
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal with Slider -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">Category Images</h5>
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

@push('scripts')
    <script src="{{ asset('admin_assets/ravindra/js/dataTables.min.js') }}"></script>
    <script src="{{ asset('admin_assets/ravindra/js/bootstrap4.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.11/clipboard.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <script>
        $(document).ready(function() {

            // Initialize DataTable
            const table = $('#data-table').DataTable({
                responsive: true,
                pageLength: 10,
                order: [
                    [2, 'asc']
                ], // Sort by Priority column (index 2) ascending
                language: {
                    search: "",
                    searchPlaceholder: "Search categories...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No entries found",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    emptyTable: "No categories found",
                    paginate: {
                        first: "<<",
                        last: ">>",
                        next: ">",
                        previous: "<"
                    }
                },
                columnDefs: [{
                    targets: -1,
                    orderable: false,
                    className: 'text-center'
                }],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control form-control-sm');
                }
            });

            // Image Modal with Slider (Bootstrap 4)
            $(document).on('click', '.category-image-thumb', function() {
                var images = $(this).data('images');
                var categoryName = $(this).data('category');

                $('#imageModalLabel').text(categoryName + ' - Images');

                // Clear previous content
                $('#carouselIndicators').empty();
                $('#carouselInner').empty();

                // Add images to carousel
                if (images && images.length > 0) {
                    images.forEach(function(img, index) {
                        var activeClass = index === 0 ? 'active' : '';

                        // Add indicator (Bootstrap 4 uses <li> elements)
                        $('#carouselIndicators').append(
                            '<li data-target="#imageCarousel" data-slide-to="' + index +
                            '" class="' + activeClass + '"></li>'
                        );

                        // Add slide
                        var imgUrl = '{{ asset('') }}' + img;
                        $('#carouselInner').append(
                            '<div class="carousel-item ' + activeClass + '">' +
                            '<img src="' + imgUrl +
                            '" class="d-block w-100" style="max-height: 500px; object-fit: contain; background: #f8f9fa;">' +
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

            // Toast notification
            function showToast(icon, message) {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });

                Toast.fire({
                    icon: icon,
                    title: message
                });
            }

            @if (session('success'))
                showToast('success', "{{ addslashes(session('success')) }}");
            @endif

            @if (session('error') || session('danger'))
                showToast('error', "{{ addslashes(session('error') ?? session('danger')) }}");
            @endif

            // Delete confirmation
            $(document).on('click', '.delete-vendor-btn', function(e) {
                e.preventDefault();
                const form = $(this).closest('form');

                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

        });
    </script>
@endpush

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/bootstrap4.min.css') }}">
    <style>
        .dataTables_wrapper .dataTables_filter input {
            margin-left: 0.5em;
            display: inline-block;
            width: auto;
        }

        /* Image thumbnail hover effect */
        .category-image-thumb {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .category-image-thumb:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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
            background: rgba(0, 0, 0, 0.3);
        }

        #imageModal .carousel-indicators button {
            background-color: #007bff;
        }

        #imageModal .modal-body {
            background: #f8f9fa;
        }
    </style>
@endpush
