@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-users-cog mr-2"></i>Sub Admins Management
            </h3>
            @include('flash_msg')
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Sub Admins</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">All Sub Admins</h4>
                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('sub-admins.create'))
                        <a href="{{ route('admin.sub-admins.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus-circle mr-1"></i> Add Sub Admin
                        </a>
                    @endif
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Roles</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subAdmins as $admin)
                                <tr>
                                    <td class="font-weight-bold text-primary">#{{ $admin->id }}</td>
                                    <td>
                                        <strong>{{ $admin->name }}</strong>
                                        @if($admin->is_super_admin)
                                            <br><span class="badge badge-danger">Super Admin</span>
                                        @endif
                                    </td>
                                    <td>{{ $admin->email }}</td>
                                    <td>
                                        @forelse($admin->roles as $role)
                                            <span class="badge badge-info">{{ $role->name }}</span>
                                        @empty
                                            <span class="badge badge-secondary">No roles</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('sub-admins.update'))
                                            @if($admin->id !== auth()->id())
                                                <form action="{{ route('admin.sub-admins.toggle-status', $admin->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm {{ $admin->is_admin ? 'btn-success' : 'btn-secondary' }}"
                                                        title="Click to toggle status"
                                                        {{ $admin->is_super_admin && !auth()->user()->isSuperAdmin() ? 'disabled' : '' }}>
                                                        {{ $admin->is_admin ? 'Active' : 'Inactive' }}
                                                    </button>
                                                </form>
                                            @else
                                                <span class="badge badge-success">Active (You)</span>
                                            @endif
                                        @else
                                            <span class="badge {{ $admin->is_admin ? 'badge-success' : 'badge-secondary' }}">
                                                {{ $admin->is_admin ? 'Active' : 'Inactive' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $admin->created_at ? $admin->created_at->format('d M Y') : '-' }}</td>
                                    <td>
                                        <div class="d-flex">
                                            @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('sub-admins.view'))
                                                <a href="{{ route('admin.sub-admins.show', $admin->id) }}"
                                                    class="btn btn-sm btn-info btn-action ml-1" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            @endif

                                            @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('sub-admins.update'))
                                                @if(!$admin->is_super_admin || auth()->user()->isSuperAdmin())
                                                    <a href="{{ route('admin.sub-admins.edit', $admin->id) }}"
                                                        class="btn btn-sm btn-primary btn-action ml-1" title="Edit Sub Admin">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                @endif
                                            @endif

                                            @if((auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('sub-admins.delete')) && $admin->id !== auth()->id())
                                                @if(!$admin->is_super_admin || auth()->user()->isSuperAdmin())
                                                    <form action="{{ route('admin.sub-admins.destroy', $admin->id) }}" method="POST"
                                                        class="d-inline delete-form ml-1">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button"
                                                            class="btn btn-sm btn-danger btn-action delete-btn" title="Delete Sub Admin">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
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
                    searchPlaceholder: "Search sub admins...",
                    info: "Showing _START_ to _END_ of _TOTAL_ sub admins",
                    emptyTable: "No sub admins found"
                },
                columnDefs: [
                    { targets: -1, orderable: false, className: 'text-center' }
                ],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control form-control-sm');
                }
            });

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

            $(document).on('click', '.delete-btn', function(e) {
                e.preventDefault();
                const form = $(this).closest('form');
                let row = $(this).closest('tr');

                Swal.fire({
                    title: 'Are you sure?',
                    text: "This sub admin will be permanently deleted.",
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
    <style>
        .dataTables_wrapper .dataTables_filter input {
            margin-left: 0.5em;
            display: inline-block;
            width: auto;
            padding: 0.375rem 0.75rem;
        }
    </style>
@endpush
