@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-seedling mr-2"></i> Hatchery Seeds
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Hatchery Seeds</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">Seed Inventory</h4>
                    @permission('hatchery-seeds.create')
                    <a href="{{ route('hatchery-seeds.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Add Seed
                    </a>
                    @endpermission
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Hatcher</th>
                                <th>Seed Brand</th>
                                <th>Count</th>
                                <th>Price</th>
                                <th>Image</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($seeds as $seed)
                                <tr>
                                    <td class="font-weight-bold text-primary">#{{ $seed->id }}</td>
                                    {{-- @dd($seed->hatchery_id); --}}
                                    <td>{{ $seed->hatchery->hatchery_name ?? 'n/a' }}</td>
                                    <td>{{ $seed->seed_brand ?? '—' }}</td>
                                    <td>{{ $seed->count ?? '0' }}</td>
                                    <td>₹{{ number_format($seed->price_per_piece, 2) }}</td>
                                    <td>
                                        @if ($seed->image_path)
                                            <img src="{{ asset($seed->image_path) }}" alt="Seed Image" class="img-thumbnail"
                                                style="width: 50px; height: 50px;">
                                        @else
                                            <span class="badge badge-secondary">No Image</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($seed->image_path)
                                            <img src="{{ asset($seed->image_path) }}" alt="Seed Image" class="img-thumbnail"
                                                style="width: 50px; height: 50px;">
                                        @else
                                            <span class="badge badge-secondary">No Image</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="d-flex">
                                            @permission('hatchery-seeds.update')
                                            <a href="{{ route('hatchery-seeds.edit', $seed->id) }}"
                                                class="btn btn-sm btn-primary btn-action ml-1" title="Edit Seed">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endpermission
                                            @permission('hatchery-seeds.delete')
                                            <form action="{{ route('hatchery-seeds.destroy', $seed->id) }}" method="POST"
                                                class="d-inline delete-form ml-1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="btn btn-sm btn-danger btn-action delete-vendor-btn"
                                                    title="Delete Seed">
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
    <script>
        $(document).ready(function() {
            $('#data-table').DataTable({
                responsive: true,
                paging: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                info: true,
                searching: true,
                ordering: true,
                order: [
                    [0, 'desc']
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search seeds...",
                    info: "Showing _START_ to _END_ of _TOTAL_ seeds",
                    emptyTable: "No seeds found"
                },
                columnDefs: [{
                        targets: -1,
                        orderable: false,
                        className: 'text-center'
                    },
                    {
                        targets: 5,
                        orderable: false
                    }
                ],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control form-control-sm');
                }
            });

            // Toasts
            @if (session('success'))
                showToast('success', "{{ addslashes(session('success')) }}");
            @endif
            @if (session('error') || session('danger'))
                showToast('error', "{{ addslashes(session('error') ?? session('danger')) }}");
            @endif

            // Delete confirmation
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
