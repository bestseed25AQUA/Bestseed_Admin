@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-dna mr-2"></i>Hatcheries
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Hatcheries</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">Registered Hatcheries</h4>
                    @permission('hatcheries.create')
                    <a href="{{ route('hatcheries.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Register Hatchery
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
                                <label class="form-label small mb-1"><i class="fas fa-toggle-on mr-1"></i>Status</label>
                                <select id="filter-status" class="form-control form-control-sm">
                                    <option value="">All Status</option>
                                    <option value="Open">Open</option>
                                    <option value="Closed">Closed</option>
                                    <option value="Coming Soon">Coming Soon</option>
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
                                <th>Hatchery Name</th>
                                <th>Shrimp Category</th>
                                <th>Fish Category</th>
                                <th>Locations/Branch</th>
                                <th>Brood Stock</th>
                                <th>Price</th>
                                <th>Vendor</th>
                                <th>Available On</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $key => $item)
                                <tr>
                                    <td data-order="{{ $item->id }}">#{{ $item->id }}</td>
                                    <td>{{ $item->hatchery_name }}</td>

                                    {{-- Shrimp Category --}}
                                    <td>
                                        @if ($item->cat_name)
                                            {{ $item->cat_name }}
                                        @else
                                            --
                                        @endif
                                    </td>

                                    {{-- Fish Category --}}
                                    <td>
                                        @if ($item->brand_id && isset($fishCategoryMap[$item->brand_id]))
                                            {{ $fishCategoryMap[$item->brand_id] }}
                                        @else
                                            --
                                        @endif
                                    </td>

                                    {{-- Locations --}}
                                    <td>
                                        @php
                                            $locationIds = is_array($item->locations)
                                                ? $item->locations
                                                : json_decode($item->locations, true) ?? [];

                                            $locationNames = array_map(fn($id) => $locations[$id] ?? '—', $locationIds);
                                        @endphp
                                        {{ implode(', ', $locationNames) }}
                                        {{ $item->loc_name }}

                                        @if (isset($hatchery_branches_map[$item->id]) && count($hatchery_branches_map[$item->id]) > 0)
                                            / {{ implode(', ', $hatchery_branches_map[$item->id]) }}
                                        @elseif (isset($hatchery_location_branches_array[$item->branch_name]))
                                            / {{ $hatchery_location_branches_array[$item->branch_name] }}
                                        @endif
                                    </td>

                                    {{-- Brood Stock --}}
                                    <td>
                                        @if ($item->broodstock_count)
                                            {{ $item->broodstock_count }}
                                        @elseif (isset($hatcherystock_array[$item->id]))
                                            {{ $hatcherystock_array[$item->id] }}
                                        @else
                                            0
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

                                    {{-- Vendor --}}
                                    <td>
                                        @php
                                            $vendorIds = json_decode($item->vendor_id, true);
                                            $vendorIds = is_array($vendorIds) ? $vendorIds : [$vendorIds];
                                            $vendorNames = array_map(fn($id) => $vendors[$id] ?? '—', $vendorIds);
                                        @endphp
                                        {{ implode(', ', $vendorNames) }}
                                    </td>

                                    {{-- Available On --}}
                                    <td>
                                        {{ $item->available_on ? \Carbon\Carbon::parse($item->available_on)->format('Y-m-d') : '—' }}
                                    </td>

                                    {{-- Status --}}
                                    <td>
                                        @if ($item->hatchery_status)
                                            <span class="badge {{ $item->status_class }}">
                                                {{ $item->status_label }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">-</span>
                                        @endif
                                        <div class="mt-1">
                                            @if ($item->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-dark">Inactive</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Actions --}}
                                    <td>
                                        <div class="d-flex gap-1" style="white-space: nowrap;">
                                            @permission('hatcheries.update')
                                            <a href="{{ route('hatcheries.edit', $item->id) }}"
                                                class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            {{-- Active / Inactive toggle --}}
                                            <form action="{{ route('hatcheries.toggle-active', $item->id) }}" method="POST"
                                                class="d-inline-block m-0">
                                                @csrf
                                                <button type="submit"
                                                    class="btn btn-sm {{ $item->is_active ? 'btn-warning' : 'btn-success' }}"
                                                    title="{{ $item->is_active ? 'Mark Inactive (hide from users)' : 'Mark Active (show to users)' }}"
                                                    onclick="return confirm('{{ $item->is_active ? 'Mark this hatchery INACTIVE? It will be hidden from users.' : 'Mark this hatchery ACTIVE? It will be visible to users.' }}');">
                                                    <i class="fas {{ $item->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                                </button>
                                            </form>
                                            @endpermission
                                            @permission('hatcheries.delete')
                                            <form action="{{ route('hatcheries.destroy', $item->id) }}" method="POST"
                                                class="d-inline-block m-0">
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
        td .d-flex {
            display: flex !important;
            flex-direction: row !important;
            align-items: center;
        }
        td .d-flex .btn {
            margin-right: 5px;
        }
        td .d-flex .btn:last-child {
            margin-right: 0;
        }
        td .d-flex form {
            margin: 0 !important;
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
                    searchPlaceholder: "Search hatcheries...",
                    emptyTable: "No hatcheries found"
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
                var statusFilter = $('#filter-status').val().toLowerCase();

                var location = data[4].toLowerCase(); // Locations column
                var shrimpCategory = data[2].toLowerCase(); // Shrimp Category
                var fishCategory = data[3].toLowerCase(); // Fish Category
                var vendor = data[7].toLowerCase(); // Vendor column
                var status = data[9].toLowerCase(); // Status column

                var locationMatch = !locationFilter || location.indexOf(locationFilter) !== -1;
                var categoryMatch = !categoryFilter || shrimpCategory.indexOf(categoryFilter) !== -1 || fishCategory.indexOf(categoryFilter) !== -1;
                var vendorMatch = !vendorFilter || vendor.indexOf(vendorFilter) !== -1;
                var statusMatch = !statusFilter || status.indexOf(statusFilter) !== -1;

                return locationMatch && categoryMatch && vendorMatch && statusMatch;
            });

            // Filter change handlers
            $('#filter-location, #filter-category, #filter-vendor, #filter-status').on('change', function() {
                table.draw();
            });

            // Clear filters
            $('#clear-filters').on('click', function() {
                $('#filter-location, #filter-category, #filter-vendor, #filter-status').val('');
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
