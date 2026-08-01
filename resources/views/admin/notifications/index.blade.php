@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-bell mr-2"></i> Push Notifications
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Push Notifications</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">Sent Notifications</h4>
                    @permission('settings.create')
                    <a href="{{ route('admin.notifications.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Send New Notification
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
                                <th>Module</th>
                                <th>Sent To</th>
                                <th>Body</th>
                                <th>Sent At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $total = $notifications->count(); @endphp
                            @foreach ($notifications as $key => $notification)
                                <tr>
                                    <td class="font-weight-bold text-primary">{{ $total - $key }}</td>
                                    <td>
                                        @if ($notification->image)
                                            <img src="{{ asset($notification->image) }}" class="img-thumbnail" style="width: 80px;">
                                        @else
                                            <span class="badge badge-secondary">No Image</span>
                                        @endif
                                    </td>
                                    <td>{{ $notification->title }}</td>
                                    <td>
                                        @if ($notification->module)
                                            <span class="badge badge-info">{{ $notification->module }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($notification->recipient_type === 'selected' && $notification->farmer)
                                            @php
                                                $farmerName = trim(($notification->farmer->first_name ?? '') . ' ' . ($notification->farmer->last_name ?? ''));
                                            @endphp
                                            <span class="badge badge-primary">{{ $farmerName ?: ($notification->farmer->mobile ?? 'Unknown') }}</span>
                                        @else
                                            <span class="badge badge-success">All Users</span>
                                        @endif
                                    </td>
                                    <td>{{ Str::limit($notification->body, 80) }}</td>
                                    <td>{{ $notification->created_at->format('d M Y, h:i A') }}</td>
                                    <td>
                                        @permission('settings.delete')
                                        <form action="{{ route('admin.notifications.destroy', $notification->id) }}" method="POST" class="d-inline delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger delete-btn" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        @endpermission
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
    <script>
        $(document).ready(function() {
            var table = $('#data-table').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[0, 'desc']],
                language: {
                    search: "",
                    searchPlaceholder: "Search notifications...",
                    emptyTable: "No notifications sent yet",
                },
                columnDefs: [
                    { targets: -1, orderable: false, className: 'text-center' },
                    { targets: 1, orderable: false }
                ],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control form-control-sm');
                }
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

            @if (session('error'))
                Swal.fire({
                    toast: true,
                    position: 'top-right',
                    icon: 'error',
                    title: "{{ addslashes(session('error')) }}",
                    showConfirmButton: false,
                    timer: 3500,
                    timerProgressBar: true
                });
            @endif

            $(document).on('click', '.delete-btn', function(e) {
                e.preventDefault();
                const form = $(this).closest('form');
                let row = $(this).closest('tr');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This action cannot be undone.",
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
@endpush
