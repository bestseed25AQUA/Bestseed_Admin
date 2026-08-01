@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title d-flex align-items-center">
            <i class="fas fa-seedling mr-2"></i>Wanted Stock Listings
        </h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Wanted Stock Listings</li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="card-title mb-0">Wanted Stock Listings</h4>

                {{-- Species filter tabs --}}
                <div class="btn-group" role="group">
                    <a href="{{ route('seed-wanted-listings.index', ['species' => 'shrimp']) }}"
                        class="btn {{ $species === 'shrimp' ? 'btn-primary' : 'btn-outline-primary' }}"
                        style="min-width: 120px;">
                        Shrimp
                    </a>
                    <a href="{{ route('seed-wanted-listings.index', ['species' => 'fish']) }}"
                        class="btn {{ $species === 'fish' ? 'btn-primary' : 'btn-outline-primary' }}"
                        style="min-width: 120px;">
                        Fish
                    </a>
                </div>

                @permission('market-prices.create')
                <a href="{{ route('seed-wanted-listings.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle mr-1"></i> Add Listing
                </a>
                @endpermission
            </div>

            <div class="table-responsive">
                <table id="data-table" class="table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>Sl. No</th>
                            <th>Title</th>
                            <th>Count / KG</th>
                            <th>Value</th>
                            <th>Payment</th>
                            <th>Minimum</th>
                            <th>Area</th>
                            <th>Price</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listings as $key => $listing)
                            <tr>
                                <td class="font-weight-bold text-primary">{{ $key + 1 }}</td>
                                <td>{{ $listing->title }}</td>
                                <td><span class="badge badge-info">{{ ucfirst($listing->count_or_kg) }}</span></td>
                                <td>{{ $listing->count_or_kg_value ?? '-' }}</td>
                                <td>{{ $listing->payment ?? '-' }}</td>
                                <td>{{ $listing->minimum ?? '-' }}</td>
                                <td>{{ $listing->area ?? '-' }}</td>
                                <td>{{ $listing->price ?? '-' }}</td>
                                <td>{{ $listing->phone ?? '-' }}</td>
                                <td>
                                    <div class="d-flex">
                                        @permission('market-prices.update')
                                        <a href="{{ route('seed-wanted-listings.edit', $listing->id) }}"
                                            class="btn btn-sm btn-primary btn-action ml-1" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endpermission

                                        @permission('market-prices.delete')
                                        <form action="{{ route('seed-wanted-listings.destroy', $listing->id) }}"
                                            method="POST" class="d-inline delete-form ml-1">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button"
                                                class="btn btn-sm btn-danger btn-action delete-btn"
                                                title="Delete">
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
                searchPlaceholder: "Search listings...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "No entries found",
                emptyTable: "No listings found",
                paginate: { first: "<<", last: ">>", next: ">", previous: "<" }
            },
            columnDefs: [{ targets: -1, orderable: false, className: 'text-center' }],
            initComplete: function () {
                $('.dataTables_filter input').addClass('form-control form-control-sm');
            }
        });

        $(document).on('click', '.delete-btn', function (e) {
            e.preventDefault();
            const form = $(this).closest('form');
            Swal.fire({
                title: 'Are you sure?',
                text: "This listing will be permanently deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });

        @if (session('success'))
            Swal.fire({
                toast: true, position: 'top-right', icon: 'success',
                title: "{{ addslashes(session('success')) }}",
                showConfirmButton: false, timer: 3500, timerProgressBar: true
            });
        @endif
    });
</script>
@endpush

@push('styles')
<style>
    .dataTables_wrapper .dataTables_filter input {
        margin-left: 0.5em; display: inline-block; width: auto;
    }
</style>
@endpush
