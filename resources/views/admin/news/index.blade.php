@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-bullhorn mr-2"></i> News Management
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">News Management</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">All News</h4>
                    @permission('news.create')
                    <a href="{{ route('news.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Add News
                    </a>
                    @endpermission
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category</th>
                                <th>Title</th>
                                <th>Media</th>
                                <th>Description</th>
                                {{-- <th>Hashtags</th> --}}
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($posts as $key => $post)
                                <tr>
                                    <td class="font-weight-bold text-primary">{{ $key + 1 }}</td>
                                    <td>{{ $post->type }}</td>
                                    <td>{{ $post->title }}</td>
                                    <td>
                                        @if ($post->media_path)
                                            @if ($post->media_type === 'video')
                                                <video width="80" controls>
                                                    <source src="{{ asset($post->media_path) }}" type="video/mp4">
                                                </video>
                                            @else
                                                <img src="{{ asset($post->media_path) }}" class="img-thumbnail"
                                                    style="width: 80px;">
                                            @endif
                                        @else
                                            <span class="badge badge-secondary">No Media</span>
                                        @endif
                                    </td>
                                    <td>{!! Str::limit($post->description, 60) !!}</td>
                                    {{-- <td>
                                @if ($post->hashtags)
                                    @foreach (explode(',', $post->hashtags) as $tag)
                                        <span class="badge badge-info">#{{ trim($tag) }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td> --}}
                                    <td>
                                        <span class="badge {{ $post->is_active ? 'badge-success' : 'badge-danger' }}">
                                            {{ $post->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            @permission('news.update')
                                            <a href="{{ route('news.edit', $post->id) }}"
                                                class="btn btn-sm btn-primary btn-action ml-1" title="Edit Post">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endpermission
                                            @permission('news.delete')
                                            <form action="{{ route('news.destroy', $post->id) }}" method="POST"
                                                class="d-inline delete-form ml-1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="btn btn-sm btn-danger btn-action delete-vendor-btn"
                                                    title="Delete Post">
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
    <script>
        $(document).ready(function() {
            var table = $('#data-table').DataTable({
                responsive: true,
                pageLength: 10,
                order: [
                    [0, 'asc']
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search posts...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No entries found",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    emptyTable: "No posts found",
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
                    },
                    {
                        targets: 2,
                        orderable: false
                    }
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

            $(document).on('click', '.delete-vendor-btn', function(e) {
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
        });


    </script>
@endpush

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
@endpush
