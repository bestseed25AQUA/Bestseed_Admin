@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title d-flex align-items-center">
            <i class="fas fa-dna mr-2"></i></i>Hatchery locations
        </h3>
        {{-- @include('flash_msg') --}}
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Hatchery locations </li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="card-title mb-0">Hatchery locations</h4>
                @permission('hatchery-locations.create')
                <a href="{{ route('hatchery-locations.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle mr-1"></i> Add Hatchery locations
                </a>
                @endpermission
            </div>

            <div class="table-responsive">
                <table id="data-table" class="table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>location Name</th>
                            <th>Priority</th>

                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data as $key => $item)
                        <tr>
                            <td class="font-weight-bold text-primary">{{ $key +1 }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm mr-3">
                                        <span class="avatar-title bg-light rounded-circle text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            {{ strtoupper(substr($item->location_name, 0, 1)) }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-weight-medium">{{ $item->location_name }}</div>
                                        <small class="text-muted">Location name</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-phone-alt text-muted mr-2"></i>
                                    {{ $item->priority ?? 'N/A' }}
                                </div>
                            </td>


                            <td>
                                <div class="d-flex">


                                    <!-- 🔸 Branches Button (Correct Place) -->
                                    <a href="{{ route('location.branches.index', $item->id) }}" 
                                    class="btn btn-sm btn-warning btn-action ml-1">
                                        <i class="fas fa-sitemap"></i> Branches
                                    </a>


                                    @permission('hatchery-locations.update')
                                    <a href="{{ route('hatchery-locations.edit', $item->id) }}"
                                       class="btn btn-sm btn-primary btn-action ml-1"
                                       title="Edit Vendor">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @endpermission
                                    @permission('hatchery-locations.delete')
                                    <form action="{{ route('hatchery-locations.destroy', $item->id) }}"
                                          method="POST"
                                          class="d-inline delete-form ml-1">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="btn btn-sm btn-danger btn-action delete-vendor-btn"
                                                title="Delete Vendor">
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

<!-- Credentials Modal -->

@endsection

@push('scripts')

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.8.3/dist/sweetalert2.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.11/clipboard.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
 $(document).ready(function() {
    const table = $('#data-table').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'asc']],
        language: {
            search: "",
            searchPlaceholder: "Search categories...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No entries found",
            infoFiltered: "(filtered from _MAX_ total entries)",
            emptyTable: "No categories found",
            paginate: {
                first: "<<",
                last: ">>",
                next: ">",
                previous: "<"
            }
        },
        // Auto-detect last column and make it unorderable
        columnDefs: [
            {
                targets: -1, // last column
                orderable: false,
                className: 'text-center'
            }
        ],
        initComplete: function() {
            $('.dataTables_filter input').addClass('form-control form-control-sm');
        }
    });
});

    // Show toast notification
function showToast(icon, message) {
    Swal.fire({
        toast: true,                 // toast mode
        position: 'top-right',       // top-right position
        showConfirmButton: false,    // no button
        timer: 3500,                 // duration in ms
        timerProgressBar: true,      // show progress bar
        icon: icon,                  // success / error / info / warning
        title: message,              // message text
        background: '#f0f4f8',       // light background
        color: '#333',               // dark text
        iconColor: icon === 'success' ? '#38b000' : '#d00000', // green/red icons
        customClass: {
            popup: 'shadow-md rounded-md p-3', // rounded with shadow
            title: 'font-medium text-sm'      // medium bold, small text
        },
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
}

    // Show success/error messages from session
@if(session('success'))
    showToast('success', "{{ addslashes(session('success')) }}");
@endif

@if(session('error') || session('danger'))
    showToast('error', "{{ addslashes(session('error') ?? session('danger')) }}");
@endif


    // Handle delete confirmation
    $(document).on('click', '.delete-vendor-btn', function(e) {
        e.preventDefault();
        const form = $(this).closest('form');

        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
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



    // Handle clipboard copy
    // const clipboard = new ClipboardJS('.copy-btn');
    // clipboard.on('success', function(e) {
    //     showToast('success', 'Copied to clipboard!');
    //     e.clearSelection();
    // });
    // clipboard.on('error', function(e) {
    //     showToast('error', 'Failed to copy. Please try again.');
    // });
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
