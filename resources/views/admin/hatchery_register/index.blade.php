@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title d-flex align-items-center">
            <i class="fas fa-store mr-2"></i> Hatchery
        </h3>
        @include('flash_msg')
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Hatchery </li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="card-title mb-0">Hatchery</h4>
                <a href="{{ route('vendors.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle mr-1"></i> Add Hatchery Register
                </a>
            </div>

            <div class="table-responsive">
                <table id="vendor-table" class="table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th></th>
                            <th>Hatchery Name</th>
                            <th>category</th>
                            <th>locations</th>
                            {{-- <th>Status</th> --}}
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data as $item)
                        <tr>
                            <td class="font-weight-bold text-primary">#{{ $item->id }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm mr-3">
                                        <span class="avatar-title bg-light rounded-circle text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            {{ strtoupper(substr($vendor->name, 0, 1)) }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-weight-medium">{{ $item->name }}</div>
                                        <small class="text-muted">Vendor</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-phone-alt text-muted mr-2"></i>
                                    {{ $vendor->mobile ?? 'N/A' }}
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="far fa-envelope text-muted mr-2"></i>
                                    {{ $vendor->email ?? 'N/A' }}
                                </div>
                            </td>
                            <td>
                                @if($vendor->status == 1)
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> Active
                                    </span>
                                @else
                                    <span class="badge badge-warning">
                                        <i class="fas fa-exclamation-circle"></i> Inactive
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex">
                                    @if($vendor->status != 1)
                                    <button class="btn btn-sm btn-info btn-action show-credentials"
                                            data-vendor-id="{{ $vendor->id }}"
                                            data-vendor-name="{{ $vendor->name }}"
                                            data-vendor-email="{{ $vendor->email ?? '' }}"
                                            data-vendor-mobile="{{ $vendor->mobile ?? '' }}"
                                            data-login-url="{{ url('/login') }}"
                                            data-toggle="modal"
                                            data-target="#credentialsModal"
                                            title="Share Credentials">
                                        <i class="fas fa-share-alt"></i>
                                    </button>
                                    @endif
                                    <a href="{{ route('vendors.edit', $vendor->id) }}"
                                       class="btn btn-sm btn-primary btn-action ml-1"
                                       title="Edit Vendor">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('vendors.destroy', $vendor->id) }}"
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
<div class="modal fade" id="credentialsModal" tabindex="-1" role="dialog" aria-labelledby="credentialsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="credentialsModalLabel">Vendor Credentials</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="credentialsBody">
                <div class="text-center p-4 loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2">Fetching credentials securely...</p>
                </div>

                <div id="credentials-data" class="d-none">
                    <p><strong>Vendor:</strong> <span id="vendorName"></span></p>
                    <div class="form-group">
                        <label for="loginId">Login ID (Best-Seed-ID)</label>
                        <div class="input-group">
                            <input type="text" id="loginId" class="form-control" readonly>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary copy-btn" type="button" data-clipboard-target="#loginId" title="Copy Login ID">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="passwordValue">Temporary Password</label>
                        <div class="input-group">
                            <input type="text" id="passwordValue" class="form-control" readonly>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary copy-btn" type="button" data-clipboard-target="#passwordValue" title="Copy Password">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    {{-- <small class="text-danger">**Warning:** Credentials are shown temporarily for sharing. Recommend password change after first login.</small> --}}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" id="whatsappShare" class="btn btn-success share-btn" disabled>
                    <i class="fab fa-whatsapp"></i> Share via WhatsApp
                </button>
                <button type="button" id="emailShare" class="btn btn-primary share-btn" disabled>
                    <i class="fas fa-envelope"></i> Share via Email
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{asset('admin_assets/ravindra/js/sweetalert.js')}}">
<link rel="stylesheet" href="{{asset('admin_assets/ravindra/js/dataTables.min.js')}}">
<link rel="stylesheet" href="{{asset('admin_assets/ravindra/js/bootstrap4.min.js')}}">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.8.3/dist/sweetalert2.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.11/clipboard.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    const table = $('#vendor-table').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'desc']],
        // dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
        //      "<'row'<'col-sm-12'tr>>" +
        //      "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            search: "",
            searchPlaceholder: "Search vendors...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No entries found",
            infoFiltered: "(filtered from _MAX_ total entries)",
            emptyTable: "No vendors found",
            paginate: {
                first: "<<",
                last: ">>",
                next: ">",
                previous: "<"
            }
        },
        columnDefs: [
            { orderable: false, targets: [5], className: 'text-center' }
        ],
        initComplete: function() {
            $('.dataTables_filter input').addClass('form-control form-control-sm');
        }
    });

    // Show toast notification
    function showToast(icon, message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        Toast.fire({
            icon: icon,
            title: message
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


    $(document).on('click', '.show-credentials', function() {
        const vendorId = $(this).data('vendor-id');
        const vendorName = $(this).data('vendor-name');
        const vendorEmail = $(this).data('vendor-email');
        const vendorMobile = $(this).data('vendor-mobile');
        const loginUrl = $(this).data('login-url');

        // Reset modal state
        $('#vendorName').text(vendorName);
        $('#loginId').val('');
        $('#passwordValue').val('');
        $('#credentials-data').addClass('d-none');
        $('.loading-spinner').removeClass('d-none');
        $('.share-btn').prop('disabled', true);

        // Show modal
        $('#credentialsModal').modal('show');

        // Fetch credentials via AJAX
        $.ajax({
            url: `/admin/vendors/${vendorId}/credentials`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const loginId = response.best_seeds_id || 'Not available';
                    const password = response.temp_password || 'Not available';

                    if (loginId) {
                        // Update form fields
                        $('#loginId').val(loginId);
                        $('#passwordValue').val(password);

                        // Update share buttons
                        const shareMessage = `Vendor Credentials\n\n` +
                            `Name: ${response.name}\n` +
                            `Login ID: ${loginId}\n` +
                            `Password: ${password}\n\n` +
                            `Login URL: ${loginUrl}`;

                        // WhatsApp share
                        $('#whatsappShare').off('click').on('click', function() {
                            window.open(`https://wa.me/?text=${encodeURIComponent(shareMessage)}`, '_blank');
                        });

                        // Email share
                        $('#emailShare').off('click').on('click', function() {
                            window.location.href = `mailto:${vendorEmail}?subject=Vendor Account Credentials&body=${encodeURIComponent(shareMessage)}`;
                        });

                        // Show data and enable buttons
                        $('.loading-spinner').addClass('d-none');
                        $('#credentials-data').removeClass('d-none');
                        $('.share-btn').prop('disabled', false);

                        // Initialize clipboard
                        new ClipboardJS('.copy-btn');
                    } else {
                        showToast('error', 'No login ID found for this vendor');
                        $('#credentialsModal').modal('hide');
                    }
                } else {
                    showToast('error', response.message || 'Failed to load credentials');
                    $('#credentialsModal').modal('hide');
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON?.message || 'Error fetching credentials';
                showToast('error', errorMsg);
                $('#credentialsModal').modal('hide');
            }
        });
    });

    // Handle clipboard copy
    const clipboard = new ClipboardJS('.copy-btn');
    clipboard.on('success', function(e) {
        showToast('success', 'Copied to clipboard!');
        e.clearSelection();
    });
    clipboard.on('error', function(e) {
        showToast('error', 'Failed to copy. Please try again.');
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
    }
</style>
@endpush
