@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-tags mr-2"></i> Best Deals
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Best Deals</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">All Best Deals</h4>
                    @permission('news.create')
                    <a href="{{ route('best-deals.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Add Best Deal
                    </a>
                    @endpermission
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Subtitle</th>
                                <th>Media</th>
                                <th>Sort Order</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deals as $key => $deal)
                                <tr>
                                    <td class="font-weight-bold text-primary">{{ $key + 1 }}</td>
                                    <td>{{ $deal->title }}</td>
                                    <td>{{ $deal->subtitle ?? '—' }}</td>
                                    <td>
                                        @if ($deal->media_path)
                                            @if ($deal->media_type === 'video')
                                                <video width="80" controls>
                                                    <source src="{{ asset($deal->media_path) }}" type="video/mp4">
                                                </video>
                                            @else
                                                <img src="{{ asset($deal->media_path) }}" class="img-thumbnail" style="width: 80px;">
                                            @endif
                                        @else
                                            <span class="badge badge-secondary">No Media</span>
                                        @endif
                                    </td>
                                    <td>{{ $deal->sort_order }}</td>
                                    <td>
                                        <span class="badge {{ $deal->is_active ? 'badge-success' : 'badge-danger' }}">
                                            {{ $deal->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            @permission('news.update')
                                            <a href="{{ route('best-deals.edit', $deal->id) }}" class="btn btn-sm btn-primary btn-action ml-1" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endpermission
                                            @permission('news.delete')
                                            <form action="{{ route('best-deals.destroy', $deal->id) }}" method="POST" class="d-inline delete-form ml-1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger btn-action delete-vendor-btn" title="Delete">
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
            $('#data-table').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[0, 'asc']],
                language: {
                    search: "",
                    searchPlaceholder: "Search best deals...",
                    emptyTable: "No best deals found",
                    paginate: { first: "<<", last: ">>", next: ">", previous: "<" }
                },
                columnDefs: [
                    { targets: -1, orderable: false, className: 'text-center' },
                ],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control form-control-sm');
                }
            });

            @if (session('success'))
                Swal.fire({ toast: true, position: 'top-right', icon: 'success', title: "{{ addslashes(session('success')) }}", showConfirmButton: false, timer: 3500, timerProgressBar: true });
            @endif

            @if (session('error') || session('danger'))
                Swal.fire({ toast: true, position: 'top-right', icon: 'error', title: "{{ addslashes(session('error') ?? session('danger')) }}", showConfirmButton: false, timer: 3500, timerProgressBar: true });
            @endif

            $(document).on('click', '.delete-vendor-btn', function(e) {
                e.preventDefault();
                const form = $(this).closest('form');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This action cannot be undone.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) form.submit();
                });
            });
        });
    </script>
@endpush

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
@endpush
