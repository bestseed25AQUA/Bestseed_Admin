@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-user-shield mr-2"></i>Roles Management
            </h3>
            @include('flash_msg')
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Roles</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">All Roles</h4>
                    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('roles.create'))
                        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus-circle mr-1"></i> Add Role
                        </a>
                    @endif
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Users</th>
                                <th>Permissions</th>
                                <th>Default</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roles as $role)
                                <tr>
                                    <td class="font-weight-bold text-primary">#{{ $role->id }}</td>
                                    <td>
                                        <strong>{{ $role->name }}</strong>
                                        @if($role->description)
                                            <br><small class="text-muted">{{ Str::limit($role->description, 50) }}</small>
                                        @endif
                                    </td>
                                    <td><code>{{ $role->slug }}</code></td>
                                    <td>
                                        <span class="badge badge-info">{{ $role->users_count }} users</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">{{ $role->permissions_count }} permissions</span>
                                    </td>
                                    <td>
                                        @if($role->is_default)
                                            <span class="badge badge-success">Default</span>
                                        @else
                                            <span class="badge badge-light">No</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('roles.view'))
                                                <a href="{{ route('admin.roles.show', $role->id) }}"
                                                    class="btn btn-sm btn-info btn-action ml-1" title="View Role">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            @endif

                                            @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('roles.update'))
                                                <a href="{{ route('admin.roles.edit', $role->id) }}"
                                                    class="btn btn-sm btn-primary btn-action ml-1" title="Edit Role">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @endif

                                            @if((auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('roles.delete')) && $role->slug !== 'super-admin')
                                                <form action="{{ route('admin.roles.destroy', $role->id) }}" method="POST"
                                                    class="d-inline delete-form ml-1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button"
                                                        class="btn btn-sm btn-danger btn-action delete-btn" title="Delete Role"
                                                        {{ $role->users_count > 0 ? 'disabled' : '' }}>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
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
                order: [[0, 'asc']],
                language: {
                    search: "",
                    searchPlaceholder: "Search roles...",
                    info: "Showing _START_ to _END_ of _TOTAL_ roles",
                    emptyTable: "No roles found"
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
                    text: "This role will be permanently deleted.",
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
