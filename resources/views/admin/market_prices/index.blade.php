@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-image mr-2"></i>Today Price List
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin') }}">
                            <i class="fas fa-home mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Today Price List</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">Today Price List</h4>
                    <div class="btn-group" role="group">
                        @foreach ($categories as $cat)
                            <a href="{{ route('market_prices.index', ['category' => $cat->id]) }}"
                                class="btn {{ $categoryId == $cat->id ? 'btn-primary' : 'btn-outline-primary' }}"
                                style="min-width: 140px; padding: 8px 24px;">
                                {{ $cat->category_name }}
                            </a>
                        @endforeach
                    </div>
                    @permission('market-prices.create')
                    <a href="{{ route('market_prices.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Add Today Price
                    </a>
                    @endpermission
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Sl. No</th>
                                <th>Priority</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Size</th>
                                <th>Price (₹)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($prices as $key => $price)
                                <tr>
                                    <td class="font-weight-bold text-primary">{{ $key + 1 }}</td>
                                    <td><span class="badge badge-info">{{ $price->priority ?? '-' }}</span></td>
                                    <td>{{ $price->category->category_name ?? "N/A" }}</td>
                                    <td>{{ $price->location->location_name ?? "N/A" }}</td>
                                    <td>{{ $price->size }}</td>
                                    <td>₹{{ number_format($price->price, 2) }}</td>
                                    <td>
                                        <div class="d-flex">
                                            @permission('market-prices.update')
                                            <a href="{{ route('market_prices.edit', $price->id) }}"
                                                class="btn btn-sm btn-primary btn-action ml-1"
                                                title="Edit Price">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endpermission

                                            @permission('market-prices.delete')
                                            <form action="{{ route('market_prices.destroy', $price->id) }}"
                                                method="POST"
                                                class="d-inline delete-form ml-1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button"
                                                    class="btn btn-sm btn-danger btn-action delete-price-btn"
                                                    title="Delete Price">
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
    $(document).ready(function () {
        $('#data-table').DataTable({
            responsive: true,
            pageLength: 10,
            order: [[0, 'asc']],
            language: {
                search: "",
                searchPlaceholder: "Search prices...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "No entries found",
                infoFiltered: "(filtered from _MAX_ total entries)",
                emptyTable: "No prices found",
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
            initComplete: function () {
                $('.dataTables_filter input').addClass('form-control form-control-sm');
            }
        });

        $(document).on('click', '.delete-price-btn', function (e) {
            e.preventDefault();
            const form = $(this).closest('form');
            Swal.fire({
                title: 'Are you sure?',
                text: "This price record will be permanently deleted.",
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
    });
</script>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            width: '100%',
            placeholder: function(){
                return $(this).data('placeholder');
            }
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
