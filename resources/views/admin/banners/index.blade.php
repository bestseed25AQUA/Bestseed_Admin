@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-image mr-2"></i>Banners
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Banners</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">All Banners</h4>
                    @permission('banners.create')
                    <a href="{{ route('banners.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Add Banner
                    </a>
                    @endpermission
                </div>
                {{--
            <form method="GET" action="{{ route('banners.index') }}" class="mb-3">
                <div class="row">
                    <div class="col-md-4">
                        <select name="screen" class="form-select">
                            <option value="">Filter by Screen</option>
                            <option value="home" {{ request('screen') == 'home' ? 'selected' : '' }}>Home</option>
                            <option value="login" {{ request('screen') == 'login' ? 'selected' : '' }}>Login</option>
                            <option value="dashboard" {{ request('screen') == 'dashboard' ? 'selected' : '' }}>Dashboard</option>
                            <option value="profile" {{ request('screen') == 'profile' ? 'selected' : '' }}>Profile</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-secondary">Apply</button>
                    </div>
                </div>
            </form> --}}

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Image</th>

                                <th>Screen</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($banners as $key => $banner)
                                {{-- @dd($banner); --}}
                                <tr>
                                    <td class="font-weight-bold text-primary">{{ $key + 1 }}</td>
                                    {{-- <td>{{ $banner->category->category_name ?? 'N?A' }}</td> --}}
                                    <td>{{ $banner->title ?? '-' }}</td>
                                    <td>
                                        @if ($banner->image)
                                            @php
                                                $ext = strtolower(pathinfo($banner->image, PATHINFO_EXTENSION));
                                            @endphp

                                            @if (in_array($ext, ['mp4', 'mov', 'avi', 'wmv', 'webm']))
                                                <a href="{{ asset($banner->image) }}" target="_blank">
                                                    <video style="height:40px;" preload="metadata">
                                                        <source src="{{ asset($banner->image) }}"
                                                            type="video/{{ $ext }}">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                </a>
                                            @else
                                                <a href="{{ asset($banner->image) }}" target="_blank">
                                                    <img src="{{ asset($banner->image) }}" alt="Banner Image"
                                                        style="height:40px;">
                                                </a>
                                            @endif
                                        @else
                                            <span class="text-muted">No image</span>
                                        @endif
                                    </td>



                                    <td>{{ ucfirst($banner->screen ?? '-') }}</td>
                                    <td>
                                        @if ($banner->status)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            @permission('banners.update')
                                            <a href="{{ route('banners.edit', $banner->id) }}"
                                                class="btn btn-sm btn-primary btn-action ml-1" title="Edit Banner">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endpermission

                                            @permission('banners.delete')
                                            <form action="{{ url('admin/banners/' . $banner->id) }}" method="POST"
                                                class="d-inline delete-form ml-1">

                                                @csrf
                                                @method('DELETE')

                                                <button type="button"
                                                    class="btn btn-sm btn-danger btn-action delete-banner-btn">
                                                    <i class="fas fa-trash"></i>
                                                </button>

                                            </form>
                                            @endpermission

                                            {{-- <form action="{{ route('banners.destroy', $banner->id) }}" method="POST"
                                                class="d-inline delete-form ml-1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-sm btn-danger btn-action delete-banner-btn" title="Delete Banner">
                                                    <i class="fas fa-trash"></i>
                                                </button> --}}
                                                {{-- <button type="button"
                                                    class="btn btn-sm btn-danger btn-action delete-vendor-btn"
                                                    title="Delete Banner">
                                                    <i class="fas fa-trash"></i>
                                                </button> --}}
                                            {{-- </form> --}}

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
    {{-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> --}}
    {{-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
    {{-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.8.3/dist/sweetalert2.all.min.js"></script> --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.11/clipboard.min.js"></script>

    {{-- <link rel="stylesheet" href="{{asset('admin_assets/ravindra/js/sweetalert2@11.js')}}"> --}}

    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/dataTables.min.js') }}">
    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/bootstrap4.min.js') }}">

    <script>
        $(document).ready(function() {
            const table = $('#data-table').DataTable({
                responsive: true,
                pageLength: 10,
                order: [
                    [0, 'asc']
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search banners...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No entries found",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    emptyTable: "No banners found",
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

            // $(document).on('click', '.delete-banner-btn', function(e) {
            //     console.log('yeah man im clicking................');
                
            //     e.preventDefault();
            //     const form = $(this).closest('form');

            //     Swal.fire({
            //         title: 'Are you sure?',
            //         text: "This banner will be permanently deleted.",
            //         icon: 'warning',
            //         showCancelButton: true,
            //         confirmButtonColor: '#d33',
            //         cancelButtonColor: '#3085d6',
            //         confirmButtonText: 'Yes, delete it!'
            //     }).then((result) => {
            //         if (result.isConfirmed) {
            //             form.submit();
            //         }
            //     });
            // });


            $(document).on('click', '.delete-banner-btn', function(e) {
    // console.log('yeah man im clicking................');
    e.preventDefault();

    const form = $(this).closest('form');

    Swal.fire({
        title: 'Are you sure?',
        text: "This banner will be permanently deleted.",
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
