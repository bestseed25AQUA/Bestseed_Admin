@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-truck mr-2"></i>Vehicle Availability
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin') }}">
                            <i class="fas fa-home mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Vehicle Availability</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">Registered Vehicles</h4>
                    @permission('vehicle-availability.create')
                    <a href="{{ route('vehicle-availability.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Register Vehicle Availability
                    </a>
                    @endpermission
                </div>

                <!-- Filters Section -->
                <div class="card bg-light mb-4">
                    <div class="card-body py-3">
                        <div class="row align-items-end">
                            <div class="col-md-3 mb-2">
                                <label class="form-label small mb-1"><i class="fas fa-map-marker-alt mr-1"></i>Location</label>
                                <select id="filter-location" class="form-control form-control-sm">
                                    <option value="">All Locations</option>
                                    @foreach ($locations as $id => $name)
                                        <option value="{{ $name }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
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
                            <div class="col-md-2 mb-2">
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
                                <th>Vehicle Name</th>
                                <th>Selected Hatchery</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Vendor</th>
                                <th>Price</th>
                                <th>Route Locations</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Available On</th>
                                <th>Available Space</th>
                                <th>Gallery</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $key => $item)
                                <tr>
                                    <td data-order="{{ $item->id }}">#{{ $item->id }}</td>

                                    {{-- Vehicle Name --}}
                                    <td>{{ $item->vehicle_name ?? '--' }}</td>

                                    {{-- Selected Hatchery --}}
                                    <td>
                                        @if ($item->hatchery_id && isset($hatcheryNames[$item->hatchery_id]))
                                            {{ $hatcheryNames[$item->hatchery_id] }}
                                        @else
                                            --
                                        @endif
                                    </td>

                                    {{-- Category --}}
                                    <td>
                                        {{ $item->category->category_name ?? '--' }}
                                    </td>

                                    {{-- Location --}}
                                    <td>
                                        {{ $item->location->location_name ?? '--' }}
                                    </td>

                                    {{-- Vendor --}}
                                    <td>
                                        {{ $item->vendor->name ?? '--' }}
                                    </td>

                                    {{-- Price --}}
                                    <td>
                                        @if ($item->price)
                                            {{ $item->price }}
                                        @else
                                            --
                                        @endif
                                    </td>

                                    {{-- Route Locations --}}
                                    <td>
                                        @php
                                            $locationIds = is_array($item->location_ids) ? $item->location_ids : [];
                                            $locationNames = array_map(fn($id) => $locations[$id] ?? '--', $locationIds);
                                        @endphp
                                        {{ implode(' → ', $locationNames) ?: '--' }}
                                    </td>

                                    {{-- Start Date --}}
                                    <td>{{ $item->start_date ? \Carbon\Carbon::parse($item->start_date)->format('d M, Y') : '--' }}</td>

                                    {{-- End Date --}}
                                    <td>{{ $item->end_date ? \Carbon\Carbon::parse($item->end_date)->format('d M, Y') : '--' }}</td>

                                    {{-- Available On --}}
                                    <td>
                                        {{ $item->available_on ? \Carbon\Carbon::parse($item->available_on)->format('d M, Y') : '--' }}
                                    </td>

                                    {{-- Available Space --}}
                                    <td>
                                        {{ $item->available_space ?? '--' }}
                                    </td>

                                    {{-- Gallery Count --}}
                                    <td>
                                        @if($item->gallery->count() > 0)
                                            <span class="badge bg-info">{{ $item->gallery->count() }} items</span>
                                        @else
                                            <span class="text-muted">--</span>
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    {{-- Expired is derived from end_date, not stored: a vehicle
                                         whose window has closed is hidden from the user app even
                                         though is_active is still 1. Editing the end date to a
                                         future day flips this straight back to Active. --}}
                                    <td>
                                        @if($item->is_expired)
                                            <span class="badge bg-secondary"
                                                title="End date {{ $item->end_date?->format('d M, Y') }} has passed — hidden from the app until the end date is extended">
                                                Expired
                                            </span>
                                        @elseif($item->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td>
                                        <div class="d-flex gap-1" style="white-space: nowrap;">
                                            @permission('vehicle-availability.update')
                                            <a href="{{ route('vehicle-availability.edit', $item->id) }}"
                                                class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endpermission
                                            @permission('vehicle-availability.delete')
                                            <form action="{{ route('vehicle-availability.destroy', $item->id) }}"
                                                method="POST" class="d-inline-block m-0">
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
                    searchPlaceholder: "Search vehicles...",
                    emptyTable: "No vehicle availability records found"
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

            @if (session('error'))
                Swal.fire({
                    toast: true,
                    position: 'top-right',
                    icon: 'error',
                    title: "{{ addslashes(session('error')) }}",
                    showConfirmButton: false,
                    timer: 3000
                });
            @endif
        });
    </script>
@endpush
