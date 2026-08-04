@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-dna mr-2"></i>Spot Hatcheries
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin') }}">
                            <i class="fas fa-home mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Spot Hatcheries</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">Registered Spot Hatcheries</h4>
                    @permission('spot-hatcheries.create')
                    <a href="{{ route('spot-hatcheries.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Register Spot Hatchery
                    </a>
                    @endpermission
                </div>

                <!-- Filters Section -->
                <div class="card bg-light mb-4">
                    <div class="card-body py-3">
                        <div class="row align-items-end">
                            <div class="col-md-4 mb-2">
                                <label class="form-label small mb-1"><i class="fas fa-map-marker-alt mr-1"></i>Location</label>
                                <select id="filter-location" class="form-control form-control-sm">
                                    <option value="">All Locations</option>
                                    @foreach ($locations as $id => $name)
                                        <option value="{{ $name }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label small mb-1"><i class="fas fa-tags mr-1"></i>Category</label>
                                <select id="filter-category" class="form-control form-control-sm">
                                    <option value="">All Categories</option>
                                    @foreach ($categories as $id => $name)
                                        <option value="{{ $name }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label small mb-1"><i class="fas fa-user mr-1"></i>Vendor</label>
                                <select id="filter-vendor" class="form-control form-control-sm">
                                    <option value="">All Vendors</option>
                                    @foreach ($vendors as $id => $name)
                                        <option value="{{ $name }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1 mb-2">
                                <button type="button" id="clear-filters" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Spot Hatchery Name</th>
                                <th>Selected Hatchery</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Vendor</th>
                                <th>Price</th>
                                <th>Broodstock</th>
                                <th>No. of Pieces</th>
                                <th>Available On</th>
                                <th>Image</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $key => $item)
                                <tr>
                                    <td data-order="{{ $item->id }}">#{{ $item->id }}</td>
                                    <td>{{ $item->hatchery_name }}</td>

                                    {{-- Selected Hatchery --}}
                                    <td>
                                        @if ($item->selected_hatchery_id && isset($hatcheryNames[$item->selected_hatchery_id]))
                                            {{ $hatcheryNames[$item->selected_hatchery_id] }}
                                        @else
                                            --
                                        @endif
                                    </td>

                                    {{-- Category --}}
                                    <td>
                                        @if ($item->category_id && isset($categories[$item->category_id]))
                                            {{ $categories[$item->category_id] }}
                                        @else
                                            --
                                        @endif
                                    </td>

                                    {{-- Location --}}
                                    <td>
                                        @if ($item->location_id && isset($locations[$item->location_id]))
                                            {{ $locations[$item->location_id] }}
                                        @else
                                            --
                                        @endif
                                    </td>

                                    {{-- Vendor --}}
                                    <td>
                                        @if ($item->vendor_id && isset($vendors[$item->vendor_id]))
                                            {{ $vendors[$item->vendor_id] }}
                                        @else
                                            --
                                        @endif
                                    </td>

                                    {{-- Price --}}
                                    <td>
                                        @if ($item->price)
                                            {{ $item->price }}
                                        @else
                                            --
                                        @endif
                                    </td>

                                    {{-- Broodstock Count --}}
                                    <td>
                                        @if ($item->broodstock_count)
                                            {{ $item->broodstock_count }}
                                        @else
                                            0
                                        @endif
                                    </td>

                                    {{-- No. of Pieces --}}
                                    <td data-order="{{ $item->no_of_pieces ?? 0 }}">
                                        @if ($item->no_of_pieces)
                                            {{ number_format($item->no_of_pieces) }}
                                        @else
                                            --
                                        @endif
                                    </td>

                                    {{-- Available On --}}
                                    <td>
                                        {{ $item->available_on ? \Carbon\Carbon::parse($item->available_on)->format('d M, Y') : '--' }}
                                    </td>

                                    {{-- Image column --}}
                                    <td>
                                        @php
                                            // Get raw value to avoid accessor issues
                                            $rawImage = $item->getRawOriginal('image');
                                            $images = [];
                                            if (!empty($rawImage)) {
                                                $decoded = json_decode($rawImage, true);
                                                if (is_array($decoded)) {
                                                    $images = $decoded;
                                                } elseif (!empty($rawImage)) {
                                                    $images = [$rawImage];
                                                }
                                            }
                                        @endphp

                                        @if (!empty($images))
                                            @php
                                                $firstImage = $images[0];
                                                $ext = strtolower(pathinfo($firstImage, PATHINFO_EXTENSION));
                                                $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'webm']);
                                            @endphp
                                            @if ($isVideo)
                                                <video src="{{ asset($firstImage) }}" width="60" height="60" class="rounded border"></video>
                                            @else
                                                <img src="{{ asset($firstImage) }}" alt="Hatchery Image" width="60" height="60" class="rounded border" style="object-fit: cover;">
                                            @endif
                                        @else
                                            --
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td>
                                        @php
                                            $isActive = in_array($item->status, ['active', 'open']);
                                        @endphp
                                        <span class="badge {{ $isActive ? 'badge-success' : 'badge-danger' }}">
                                            {{ $isActive ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>

                                    {{-- Actions --}}
                                    <td>
                                        <div class="d-flex gap-1" style="white-space: nowrap;">
                                            @permission('spot-hatcheries.update')
                                            <a href="{{ route('spot-hatcheries.edit', $item->id) }}" class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endpermission
                                            @permission('spot-hatcheries.delete')
                                            <form action="{{ route('spot-hatcheries.destroy', $item->id) }}" method="POST" class="d-inline-block m-0">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger delete-btn" title="Delete">
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
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/bootstrap4.min.css') }}">
    <style>
        .dataTables_wrapper .dataTables_filter input {
            margin-left: 0.5em;
            display: inline-block;
            width: auto;
        }
        .card.bg-light {
            background-color: #f8f9fa !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('admin_assets/ravindra/js/dataTables.min.js') }}"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            var table = $('#data-table').DataTable({
                responsive: true,
                paging: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                info: true,
                ordering: true,
                searching: true,
                order: [[0, 'desc']],
                columnDefs: [{
                    targets: -1,
                    orderable: false,
                    className: 'text-center'
                }],
                language: {
                    search: "",
                    searchPlaceholder: "Search spot hatcheries...",
                    emptyTable: "No spot hatcheries found"
                },
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control form-control-sm');
                }
            });

            // Custom filter function
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                var locationFilter = $('#filter-location').val().toLowerCase();
                var categoryFilter = $('#filter-category').val().toLowerCase();
                var vendorFilter = $('#filter-vendor').val().toLowerCase();

                var location = data[4].toLowerCase(); // Location column
                var category = data[3].toLowerCase(); // Category column
                var vendor = data[5].toLowerCase(); // Vendor column

                var locationMatch = !locationFilter || location.indexOf(locationFilter) !== -1;
                var categoryMatch = !categoryFilter || category.indexOf(categoryFilter) !== -1;
                var vendorMatch = !vendorFilter || vendor.indexOf(vendorFilter) !== -1;

                return locationMatch && categoryMatch && vendorMatch;
            });

            // Filter change handlers
            $('#filter-location, #filter-category, #filter-vendor').on('change', function() {
                table.draw();
            });

            // Clear filters
            $('#clear-filters').on('click', function() {
                $('#filter-location, #filter-category, #filter-vendor').val('');
                table.draw();
            });

            // Delete confirmation
            $(document).on('click', '.delete-btn', function(e) {
                e.preventDefault();
                const form = $(this).closest('form');
                let row = $(this).closest('tr');

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
                        $.ajax({
                            url: form.attr('action'),
                            type: 'POST',
                            data: form.serialize(),
                            success: function() {
                                table.row(row).remove().draw(false);
                                Swal.fire({ toast: true, position: 'top-right', icon: 'success', title: 'Deleted successfully', showConfirmButton: false, timer: 3000 });
                            },
                            error: function() {
                                Swal.fire({ toast: true, position: 'top-right', icon: 'error', title: 'Failed to delete', showConfirmButton: false, timer: 3000 });
                            }
                        });
                    }
                });
            });

            // Toast notifications
            @if (session('success'))
                Swal.fire({
                    toast: true,
                    position: 'top-right',
                    icon: 'success',
                    title: "{{ addslashes(session('success')) }}",
                    showConfirmButton: false,
                    timer: 3000
                });
            @endif
        });
    </script>
@endpush
