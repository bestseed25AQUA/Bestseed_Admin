@extends('admin.layouts.main')

@section('page_title', 'Announcements')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-bullhorn mr-2"></i>Announcements
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Announcements</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">All Announcements</h4>
                    @permission('announcements.create')
                        <a href="{{ route('announcements.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus-circle mr-1"></i> Add New
                        </a>
                    @endpermission
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Audience</th>
                                <th>Read By</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($announcements as $key => $announcement)
                                <tr>
                                    <td class="font-weight-bold text-primary">{{ $key + 1 }}</td>
                                    <td>
                                        @if ($announcement->image)
                                            <a href="{{ asset($announcement->image) }}" target="_blank">
                                                <img src="{{ asset($announcement->image) }}" alt="Announcement"
                                                    style="height:40px;">
                                            </a>
                                        @else
                                            <span class="text-muted">No image</span>
                                        @endif
                                    </td>
                                    <td>{{ $announcement->title }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit(strip_tags($announcement->description), 70) }}</td>
                                    <td>
                                        @php
                                            $audienceBadge = [
                                                'user' => 'bg-info',
                                                'driver' => 'bg-warning',
                                                'vendor' => 'bg-primary',
                                            ][$announcement->audience] ?? 'bg-secondary';
                                        @endphp
                                        <span class="badge {{ $audienceBadge }}">{{ $announcement->audience_label }}</span>
                                    </td>
                                    <td>{{ $announcement->read_count }}</td>
                                    <td>
                                        @if ($announcement->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $announcement->created_at?->format('d M Y, h:i A') }}</td>
                                    <td>
                                        <div class="d-flex">
                                            @permission('announcements.update')
                                                <form action="{{ route('announcements.toggle-status', $announcement->id) }}"
                                                    method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        class="btn btn-sm {{ $announcement->is_active ? 'btn-secondary' : 'btn-success' }} btn-action"
                                                        title="{{ $announcement->is_active ? 'Deactivate' : 'Activate' }}">
                                                        <i class="fas {{ $announcement->is_active ? 'fa-toggle-off' : 'fa-toggle-on' }}"></i>
                                                    </button>
                                                </form>

                                                <a href="{{ route('announcements.edit', $announcement->id) }}"
                                                    class="btn btn-sm btn-primary btn-action ml-1" title="Edit Announcement">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @endpermission

                                            @permission('announcements.delete')
                                                <form action="{{ route('announcements.destroy', $announcement->id) }}"
                                                    method="POST" class="d-inline delete-form ml-1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button"
                                                        class="btn btn-sm btn-danger btn-action delete-announcement-btn"
                                                        title="Delete Announcement">
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
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/dataTables.min.js') }}">
    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/bootstrap4.min.js') }}">

    <script>
        $(document).ready(function() {
            $('#data-table').DataTable({
                responsive: true,
                pageLength: 10,
                order: [
                    [0, 'asc']
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search announcements...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No entries found",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    emptyTable: "No announcements found",
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

            $(document).on('click', '.delete-announcement-btn', function(e) {
                e.preventDefault();

                const form = $(this).closest('form');

                Swal.fire({
                    title: 'Are you sure?',
                    text: "This announcement will be permanently deleted.",
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

            @if (session('success'))
                Swal.fire({
                    toast: true,
                    position: 'top-right',
                    icon: 'success',
                    title: "{{ addslashes(session('success')) }}",
                    showConfirmButton: false,
                    timer: 3500,
                    timerProgressBar: true
                });
            @endif

            @if (session('error') || session('danger'))
                Swal.fire({
                    toast: true,
                    position: 'top-right',
                    icon: 'error',
                    title: "{{ addslashes(session('error') ?? session('danger')) }}",
                    showConfirmButton: false,
                    timer: 3500,
                    timerProgressBar: true
                });
            @endif
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
