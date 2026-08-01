@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title d-flex align-items-center">
            <i class="fas fa-store mr-2"></i> Vendor Management
        </h3>
        @include('flash_msg')
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Vendors</li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="card-title mb-0">Vendor List</h4>
                @permission('vendors.create')
                <a href="{{ route('vendors.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle mr-1"></i> Add New Vendor
                </a>
                @endpermission
            </div>

            <div class="table-responsive">
                <table id="vendor-table" class="table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vendor Name</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Hatchery</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vendors as $vendor)
                        <tr>
                            <td class="font-weight-bold text-primary">#{{ $vendor->best_seeds_id }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm mr-3">
                                        <span class="avatar-title bg-light rounded-circle text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            {{ strtoupper(substr($vendor->name, 0, 1)) }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-weight-medium">{{ $vendor->name }}</div>
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
                            <td>{{ $vendor->current_location_address ?? '—' }}</td>
                            <td>
                                @if($vendor->hatcheries->count() > 0)
                                    <div>{{ $vendor->hatcheries->sortByDesc('created_at')->first()->hatchery_name }}</div>
                                    @if($vendor->hatcheries->count() > 1)
                                        <a href="javascript:void(0)" class="text-primary small" onclick="showHatcheries('{{ addslashes($vendor->name) }}', {{ $vendor->id }})">
                                            +{{ $vendor->hatcheries->count() - 1 }} more
                                        </a>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($vendor->status == 1)
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> Enabled
                                    </span>
                                @else
                                    <span class="badge badge-warning">
                                        <i class="fas fa-exclamation-circle"></i> Disabled
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
                                    @permission('vendors.update')
                                    <button class="btn btn-sm btn-warning btn-action ml-1 reset-password-btn"
                                            data-vendor-id="{{ $vendor->id }}"
                                            data-vendor-name="{{ $vendor->name }}"
                                            title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    @endpermission
                                    @permission('vendors.update')
                                    <form action="{{ route('vendors.force-logout', $vendor->id) }}"
                                          method="POST"
                                          class="d-inline force-logout-form ml-1">
                                        @csrf
                                        <button type="submit"
                                                class="btn btn-sm btn-secondary btn-action force-logout-btn"
                                                data-vendor-name="{{ $vendor->name }}"
                                                title="Force Logout">
                                            <i class="fas fa-sign-out-alt"></i>
                                        </button>
                                    </form>
                                    @endpermission
                                    @permission('vendors.update')
                                    <a href="{{ route('vendors.edit', $vendor->id) }}"
                                       class="btn btn-sm btn-primary btn-action ml-1"
                                       title="Edit Vendor">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @endpermission
                                    @permission('vendors.delete')
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

<!-- Hatcheries Modal -->
<div class="modal fade" id="hatcheriesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title mb-0"><i class="fas fa-industry mr-2"></i> <span id="hatcheriesModalTitle">Hatcheries</span></h6>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0" style="max-height: 400px; overflow-y: auto;">
                <div class="list-group list-group-flush" id="hatcheriesModalList"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{"admin_assets/ravindra/js/sweetalert2@11"}}"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.8.3/dist/sweetalert2.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.11/clipboard.min.js"></script>

<script>
$(document).ready(function() {
    // Hatchery data per vendor
    var vendorHatcheries = {
        @foreach($vendors as $vendor)
            @if($vendor->hatcheries->count() > 0)
                @php
                    $sorted = $vendor->hatcheries->sortByDesc('created_at');
                    $data = $sorted->map(function($h) {
                        return [
                            'name' => $h->hatchery_name,
                        ];
                    })->values()->toArray();
                @endphp
                {{ $vendor->id }}: {!! json_encode($data) !!},
            @endif
        @endforeach
    };

    window.showHatcheries = function(vendorName, vendorId) {
        var hatcheries = vendorHatcheries[vendorId] || [];
        $('#hatcheriesModalTitle').text(vendorName + ' — Hatcheries (' + hatcheries.length + ')');
        var html = '';
        hatcheries.forEach(function(h) {
            html += '<div class="list-group-item py-2"><i class="fas fa-industry mr-2 text-muted"></i>' + h.name + '</div>';
        });
        $('#hatcheriesModalList').html(html);
        $('#hatcheriesModal').modal('show');
    };

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
            { orderable: false, targets: [6], className: 'text-center' }
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

    // Handle reset password
    $(document).on('click', '.reset-password-btn', function(e) {
        e.preventDefault();
        const vendorId = $(this).data('vendor-id');
        const vendorName = $(this).data('vendor-name');

        Swal.fire({
            title: 'Reset Password',
            html: `Are you sure you want to reset the password for <b>${vendorName}</b>?<br><small class="text-muted">A new temporary password will be generated.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f0ad4e',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-key mr-1"></i> Yes, Reset Password'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/admin/vendors/${vendorId}/reset-password`,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Password Reset Successful',
                                html: `<div class="text-left">
                                    <p><strong>Vendor:</strong> ${response.vendor_name}</p>
                                    <p><strong>Login ID:</strong> <span id="swal-login-id">${response.best_seeds_id}</span>
                                        <button class="btn btn-sm btn-outline-secondary ml-2 swal-copy-btn" data-copy="${response.best_seeds_id}" title="Copy"><i class="fas fa-copy"></i></button></p>
                                    <p><strong>New Password:</strong> <span id="swal-password">${response.temp_password}</span>
                                        <button class="btn btn-sm btn-outline-secondary ml-2 swal-copy-btn" data-copy="${response.temp_password}" title="Copy"><i class="fas fa-copy"></i></button></p>
                                    <hr>
                                    <small class="text-muted">Share these credentials with the vendor securely.</small>
                                </div>`,
                                icon: 'success',
                                showCancelButton: true,
                                confirmButtonText: '<i class="fab fa-whatsapp mr-1"></i> Share via WhatsApp',
                                confirmButtonColor: '#25D366',
                                cancelButtonText: 'Close',
                                cancelButtonColor: '#6c757d',
                                didOpen: () => {
                                    document.querySelectorAll('.swal-copy-btn').forEach(btn => {
                                        btn.addEventListener('click', function() {
                                            navigator.clipboard.writeText(this.dataset.copy);
                                            showToast('success', 'Copied to clipboard!');
                                        });
                                    });
                                }
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    const msg = `Vendor Credentials\n\nName: ${response.vendor_name}\nLogin ID: ${response.best_seeds_id}\nPassword: ${response.temp_password}`;
                                    window.open(`https://wa.me/${response.mobile}?text=${encodeURIComponent(msg)}`, '_blank');
                                }
                            });
                        }
                    },
                    error: function(xhr) {
                        showToast('error', xhr.responseJSON?.message || 'Failed to reset password');
                    }
                });
            }
        });
    });


    // Handle force logout
    $(document).on('click', '.force-logout-btn', function(e) {
        e.preventDefault();
        const form = $(this).closest('form');
        const vendorName = $(this).data('vendor-name');

        Swal.fire({
            title: 'Force Logout',
            html: `Are you sure you want to force logout <b>${vendorName}</b>?<br><small class="text-muted">The vendor will be immediately logged out of the mobile app.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#6c757d',
            cancelButtonColor: '#3085d6',
            confirmButtonText: '<i class="fas fa-sign-out-alt mr-1"></i> Yes, Force Logout'
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
<link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/bootstrap4.min.css') }}">
<style>
    .dataTables_wrapper .dataTables_filter input {
        margin-left: 0.5em;
        display: inline-block;
        width: auto;
    }
</style>
@endpush
