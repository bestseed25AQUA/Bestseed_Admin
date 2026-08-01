@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-seedling mr-2"></i>Seed Requests (From App)
            </h3>
            @include('flash_msg')
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Seed Requests</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">All Seed Requests from Farmers</h4>
                    <div>
                        <span class="badge badge-warning mr-2">Pending: {{ $seedRequests->where('status', 'pending')->count() }}</span>
                        <span class="badge badge-info mr-2">Processing: {{ $seedRequests->where('status', 'processing')->count() }}</span>
                        <span class="badge badge-success">Completed: {{ $seedRequests->where('status', 'completed')->count() }}</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Category</th>
                                <th>Hatchery</th>
                                <th>Quantity</th>
                                <th>Packing Date</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($seedRequests as $request)
                                <tr>
                                    <td class="font-weight-bold text-primary">#{{ $request->id }}</td>
                                    <td>
                                        <div class="font-weight-medium">{{ $request->name }}</div>
                                        <small class="text-muted">{{ $request->mobile }}</small>
                                    </td>
                                    <td>{{ $request->category->category_name ?? 'N/A' }}</td>
                                    <td>
                                        @if($request->hatchery_name)
                                            {{ $request->hatchery_name }}
                                        @elseif($request->hatchery)
                                            {{ $request->hatchery->hatchery_name }}
                                        @else
                                            <span class="text-muted">Not specified</span>
                                        @endif
                                    </td>
                                    <td><span class="badge badge-secondary">{{ $request->quantity }} pcs</span></td>
                                    <td>{{ $request->packing_date ? $request->packing_date->format('d M Y') : 'N/A' }}</td>
                                    <td>
                                        <small>{{ Str::limit($request->dropping_location, 30) }}</small>
                                    </td>
                                    <td>
                                        @php
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'completed' => 'success',
                                                'rejected' => 'danger',
                                            ];
                                        @endphp
                                        <span class="badge badge-{{ $statusColors[$request->status] ?? 'secondary' }}">
                                            {{ ucfirst($request->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <button type="button" class="btn btn-sm btn-info btn-action ml-1"
                                                data-toggle="modal" data-target="#viewModal{{ $request->id }}"
                                                title="View & Update">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <form action="{{ route('admin.seed-requests.destroy', $request->id) }}" method="POST"
                                                class="d-inline delete-form ml-1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-sm btn-danger btn-action delete-btn"
                                                    title="Delete Request">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- View/Edit Modal -->
                                <div class="modal fade" id="viewModal{{ $request->id }}" tabindex="-1" role="dialog">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Seed Request #{{ $request->id }}</h5>
                                                <button type="button" class="close" data-dismiss="modal">
                                                    <span>&times;</span>
                                                </button>
                                            </div>
                                            <form action="{{ route('admin.seed-requests.updateStatus', $request->id) }}" method="POST">
                                                @csrf
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6 class="text-muted mb-3">Customer Details</h6>
                                                            <p><strong>Name:</strong> {{ $request->name }}</p>
                                                            <p><strong>Mobile:</strong> {{ $request->mobile }}</p>
                                                            <p><strong>Farmer:</strong> {{ $request->farmer->name ?? 'N/A' }}</p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6 class="text-muted mb-3">Request Details</h6>
                                                            <p><strong>Category:</strong> {{ $request->category->category_name ?? 'N/A' }}</p>
                                                            <p><strong>Hatchery:</strong> {{ $request->hatchery_name ?? $request->hatchery->hatchery_name ?? 'Not specified' }}</p>
                                                            <p><strong>Quantity:</strong> {{ $request->quantity }} pieces</p>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-3">
                                                        <div class="col-md-6">
                                                            <p><strong>Packing Date:</strong> {{ $request->packing_date ? $request->packing_date->format('d M Y') : 'N/A' }}</p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>Dropping Location:</strong> {{ $request->dropping_location }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-3">
                                                        <div class="col-md-12">
                                                            <p><strong>Requested On:</strong> {{ $request->created_at->format('d M Y, H:i') }}</p>
                                                            @if($request->handler)
                                                                <p><strong>Last Updated By:</strong> {{ $request->handler->name }} on {{ $request->handled_at->format('d M Y, H:i') }}</p>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <hr>

                                                    <h6 class="text-muted mb-3">Admin Actions</h6>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="status{{ $request->id }}">Update Status</label>
                                                                <select name="status" id="status{{ $request->id }}" class="form-control">
                                                                    @foreach($statuses as $key => $label)
                                                                        <option value="{{ $key }}" {{ $request->status == $key ? 'selected' : '' }}>
                                                                            {{ $label }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="form-group">
                                                                <label for="admin_notes{{ $request->id }}">Admin Notes</label>
                                                                <textarea name="admin_notes" id="admin_notes{{ $request->id }}"
                                                                    class="form-control" rows="3"
                                                                    placeholder="Add notes about this request...">{{ $request->admin_notes }}</textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

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

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('admin_assets/ravindra/js/dataTables.min.js') }}"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

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
                    searchPlaceholder: "Search requests...",
                    info: "Showing _START_ to _END_ of _TOTAL_ requests",
                    emptyTable: "No seed requests found"
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
                    text: "This request will be permanently deleted.",
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
