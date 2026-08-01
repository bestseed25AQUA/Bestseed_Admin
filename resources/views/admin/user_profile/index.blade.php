@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-users mr-2"></i> User Profiles
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">User Profiles</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">All Users</h4>
                    {{-- <a href="{{ route('users.create') }}" class="btn btn-primary">
                    <i class="fas fa-user-plus mr-1"></i> Add User
                </a> --}}
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Profile</th>
                                <th>Name</th>
                                <th>Mobile</th>
                                {{-- <th>Email</th> --}}
                                <th>Language</th>
                                <th>Location</th>
                                {{-- <th>Status</th> --}}
                                <th>Registered On</th>
                                <th>Last Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $key => $user)
                                <tr>
                                    <td class="font-weight-bold text-primary" data-order="{{ $user->id }}">
                                        #{{ $user->id }}
                                    </td>
                                    <td>
                                        @if ($user->profile_image)
                                            <img src="{{ $user->profile_image }}" class="img-thumbnail"
                                                style="width: 60px; border-radius:50%;">
                                        @else
                                            <span class="badge badge-secondary">No Image</span>
                                        @endif
                                    </td>
                                    <td>{{ $user->first_name }} {{ $user->last_name }}</td>
                                    <td>{{ $user->mobile }}</td>
                                    {{-- <td>{{ $user->email ?? '—' }}</td> --}}
                                    <td><span class="badge badge-info">{{ strtoupper($user->language ?? 'EN') }}</span></td>
                                    <td>{{ $user->latestLocation->location_name ?? '—' }}</td>
                                    {{-- <td>
                                        <span class="badge {{ $user->is_active ? 'badge-success' : 'badge-danger' }}">
                                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td> --}}
                                    <td>{{ $user->created_at->format('d M Y') }}</td>
                                    <td>
                                        @if($user->last_active_at)
                                            {{ $user->last_active_at->format('d M Y, h:i A') }}
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="d-flex">
                                            @permission('users.update')
                                            <a href="{{ route('user_profile.edit', $user->id) }}"
                                                class="btn btn-sm btn-primary btn-action ml-1" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            {{-- Force Logout: signs the user out of their mobile device.
                                                 Use only when the user has app issues. --}}
                                            <form action="{{ route('user_profile.force-logout', $user->id) }}"
                                                method="POST" class="d-inline force-logout-form ml-1">
                                                @csrf
                                                <button type="button"
                                                    class="btn btn-sm btn-warning btn-action force-logout-btn"
                                                    title="Force Logout (sign out from mobile)">
                                                    <i class="fas fa-sign-out-alt"></i>
                                                </button>
                                            </form>
                                            @endpermission
                                            @permission('users.delete')
                                            <form action="{{ route('user_profile.destroy', $user->id) }}" method="POST"
                                                class="d-inline delete-form ml-1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="btn btn-sm btn-danger btn-action delete-user-btn"
                                                    title="Delete User">
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
                paging: true, // ENABLE DataTables pagination
                pageLength: 10, // Records per page
                info: true, // Show "Showing X to Y of Z entries"
                searching: true,
                ordering: true,
                order: [
                    [0, 'desc']
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search users...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    emptyTable: "No users found"
                },
                columnDefs: [{
                        targets: -1,
                        orderable: false
                    },
                    {
                        targets: 1,
                        orderable: false
                    }
                ]
            });

            $(document).on('click', '.delete-user-btn', function(e) {
                e.preventDefault();
                let form = $(this).closest('form');
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

            // Force Logout — confirm, then sign the user out of their mobile.
            $(document).on('click', '.force-logout-btn', function(e) {
                e.preventDefault();
                let form = $(this).closest('form');
                Swal.fire({
                    title: 'Force logout this user?',
                    text: "They will be signed out of the app and must log in again. Use only when the user is facing app issues.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e0a800',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, log them out'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: form.attr('action'),
                            type: 'POST',
                            data: form.serialize(),
                            success: function() {
                                Swal.fire({ toast: true, position: 'top-right', icon: 'success', title: 'User logged out', showConfirmButton: false, timer: 3000 });
                            },
                            error: function() {
                                Swal.fire({ toast: true, position: 'top-right', icon: 'error', title: 'Failed to log out user', showConfirmButton: false, timer: 3000 });
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
