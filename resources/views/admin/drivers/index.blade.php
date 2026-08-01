@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-id-card mr-2"></i>Drivers
            </h3>
            @include('flash_msg')
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Drivers</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">All Drivers</h4>
                    @permission('drivers.create')
                    <a href="{{ route('drivers.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Add Driver
                    </a>
                    @endpermission
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Profile</th>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Location</th>
                                <th>Location Perm.</th>
                                <th>Battery (BG)</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($drivers as $driver)
                                <tr>
                                    <td class="font-weight-bold text-primary">#{{ $driver->id }}</td>
                                    <td>
                                        @if ($driver->profile_image)
                                            <a href="{{ asset($driver->profile_image) }}" target="_blank">
                                                <img src="{{ asset($driver->profile_image) }}" alt="Profile" style="height:40px; width:40px; border-radius:50%; object-fit:cover;">
                                            </a>
                                        @else
                                            <div style="height:40px; width:40px; border-radius:50%; background:#e9ecef; display:flex; align-items:center; justify-content:center;">
                                                <i class="fas fa-user text-muted"></i>
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ $driver->name }}</td>
                                    <td>{{ $driver->mobile }}</td>
                                    <td>{{ $driver->current_location_address ?? '—' }}</td>
                                    <td>
                                        @if ($driver->location_permission)
                                            <span class="badge badge-success"><i class="fas fa-map-marker-alt mr-1"></i>On</span>
                                        @else
                                            <span class="badge badge-danger"><i class="fas fa-map-marker-alt mr-1"></i>Off</span>
                                        @endif
                                        @if ($driver->permissions_updated_at)
                                            <br><small class="text-muted">{{ $driver->permissions_updated_at->format('d M, H:i') }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($driver->battery_optimization_disabled)
                                            <span class="badge badge-success"><i class="fas fa-battery-full mr-1"></i>On</span>
                                        @else
                                            <span class="badge badge-danger"><i class="fas fa-battery-quarter mr-1"></i>Off</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form action="{{ route('drivers.toggle-status', $driver->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm {{ $driver->status ? 'btn-success' : 'btn-secondary' }}" title="Click to toggle status">
                                                {{ $driver->status ? 'Active' : 'Inactive' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>{{ $driver->created_at ? $driver->created_at->format('d M Y') : '-' }}</td>
                                    <td>
                                        <div class="d-flex">
                                            @permission('drivers.update')
                                            <a href="{{ route('drivers.edit', $driver->id) }}"
                                                class="btn btn-sm btn-primary btn-action ml-1" title="Edit Driver">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endpermission

                                            @permission('drivers.delete')
                                            <form action="{{ route('drivers.destroy', $driver->id) }}" method="POST"
                                                class="d-inline delete-form ml-1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button"
                                                    class="btn btn-sm btn-danger btn-action delete-driver-btn" title="Delete Driver">
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
                searching: true,
                ordering: true,
                order: [[0, 'desc']],
                language: {
                    search: "",
                    searchPlaceholder: "Search drivers...",
                    info: "Showing _START_ to _END_ of _TOTAL_ drivers",
                    emptyTable: "No drivers found"
                },
                columnDefs: [
                    { targets: 1, orderable: false }, // Profile column
                    { targets: -1, orderable: false, className: 'text-center' } // Actions column
                ],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control form-control-sm');
                }
            });

            // Toasts
            function showToast(icon, message) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: icon,
                    title: message,
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
            }

            @if (session('success'))
                showToast('success', "{{ addslashes(session('success')) }}");
            @endif

            @if (session('error') || session('danger'))
                showToast('error', "{{ addslashes(session('error') ?? session('danger')) }}");
            @endif

            // Delete confirmation
            $(document).on('click', '.delete-driver-btn', function(e) {
                e.preventDefault();
                let form = $(this).closest('form');
                let row = $(this).closest('tr');

                Swal.fire({
                    title: 'Are you sure?',
                    text: "This driver will be permanently deleted.",
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
    </style>
@endpush
