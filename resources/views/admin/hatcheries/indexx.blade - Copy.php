<!-- @extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-dna mr-2"></i>Hatcheries  
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Hatcheries</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">Registered Hatcheries</h4>
                    <a href="{{ route('hatcheries.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Register Hatchery
                    </a>
                </div>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Hatchery Name</th>
                                <th>Shrimp Category</th>
                                <th>Fish Category</th>
                                <th>Locations/Branch</th>
                                <th>Brood Stock</th>
                                <th>Price</th>
                                <th>Vendor</th>
                                <th>Opening</th>
                                <th>Closing</th>
                                <th>Available On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>

                            
                            {{-- @foreach ($data as $key => $item) --}}
                            @foreach ($finalData as $key => $item)
                            <tr>
                                   
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $item->hatchery_name }}</td>


                                    {{-- Categories --}}
                                <td>
                                   {{-- @php
                                        $categoryIds = is_array($item->categories)
                                            ? $item->categories
                                            : (json_decode($item->categories, true) ?? []);

                                        $categoryNames = array_map(fn($id) => $categories[$id] ?? '—', $categoryIds);
                                    @endphp --}}

                                   @php
                                    // Step 1: Decode JSON safely
                                    $categoryIds = json_decode($item->categories, true);

                                    // Step 2: If invalid → convert to empty array
                                    if (!is_array($categoryIds)) {
                                        $categoryIds = [];
                                    }

                                    // Step 3: Ensure each ID is scalar (integer/string)
                                    $categoryIds = array_filter($categoryIds, fn($id) => is_scalar($id));

                                    // Step 4: Map category names safely
                                    $categoryNames = array_map(fn($id) => $categories[$id] ?? '—', $categoryIds);
                                    @endphp

                                    {{ implode(', ', $categoryNames) }}
                                    {{$item->cat_name}} 
                                </td>
                                <td>
                                    
                                        {{-- @if(isset($brand_name_array[$item->brand_id]))
                                        {{ $brand_name_array[$item->brand_id] }}
                                        @endif --}}
                                        @php
                                            $brandIds = json_decode($item->brands, true) ?? [];
                                            $brandNames = [];

                                            foreach ($brandIds as $id) {
                                                $brandNames[] = $brand_name_array[$id] ?? '—';
                                            }
                                        @endphp

                                            {{ implode(', ', $brandNames) }}

                                    
                                </td>

                                {{-- Locations --}}  
                               
                                <td> 
                                    @php
                                        $locationIds = is_array($item->locations)
                                            ? $item->locations
                                            : (json_decode($item->locations, true) ?? []);

                                        $locationNames = array_map(fn($id) => $locations[$id] ?? '—', $locationIds);
                                    @endphp
                                    {{ implode(', ', $locationNames) }}
                                      {{$item->loc_name}} 

                                     @if(isset($hatchery_location_branches_array[$item->branch_name]))
                                        /{{ $hatchery_location_branches_array[$item->branch_name] }}
                                        @endif

                                     
                                </td>
                                <td>

                                    @if(isset($hatcherystock_array[$item->id]))
                                        {{ $hatcherystock_array[$item->id] }}
                                     @endif
                                </td>
                                <td>

                                     {{-- @if(isset($hatchery_price_array[$item->location_id][$item->category_id]))
                                        {{ $hatchery_price_array[$item->location_id][$item->category_id] }}
                                     @endif --}}

                                          @php
                                                // $prices = json_decode($item->category_prices, true);
                                                $prices = $item->category_prices ?? [];
                                                $totalPrices = [];

                                                // shrimp prices
                                                if (!empty($prices['shrimp'])) {
                                                    foreach ($prices['shrimp'] as $p) {
                                                        $totalPrices[] = $p;
                                                    }
                                                }

                                                // fish prices
                                                if (!empty($prices['fish'])) {
                                                    foreach ($prices['fish'] as $p) {
                                                        $totalPrices[] = $p;
                                                    }
                                                }
                                            @endphp

                                            {{ implode(', ', $totalPrices) }}



                                </td>


                                    {{-- Categories --}}
                                   
                                    {{-- <td>
                                        @php
                                            $categoryIds = json_decode($item->category_id, true);
                                            $categoryIds = is_array($categoryIds) ? $categoryIds : [$categoryIds];
                                            $categoryNames = array_map(
                                                fn($id) => $categories[$id] ?? '—',
                                                $categoryIds,
                                            );
                                        @endphp
                                        {{ implode(', ', $categoryNames) }}
                                    </td> --}}

                                    {{-- Locations --}}
                                    {{-- <td>
                                        @php
                                            $locationIds = json_decode($item->location_id, true);
                                            $locationIds = is_array($locationIds) ? $locationIds : [$locationIds];
                                            $locationNames = array_map(fn($id) => $locations[$id] ?? '—', $locationIds);
                                        @endphp
                                        {{ implode(', ', $locationNames) }}
                                    </td> --}}

                                    {{-- Vendors --}}
                                    
                                    <td>
                                        @php
                                            $vendorIds = json_decode($item->vendor_id, true);
                                            $vendorIds = is_array($vendorIds) ? $vendorIds : [$vendorIds];
                                            $vendorNames = array_map(fn($id) => $vendors[$id] ?? '—', $vendorIds);
                                        @endphp
                                        {{ implode(', ', $vendorNames) }}
                                         {{-- {{$item->cat_name}} --}}
                                    </td>

                                    <td>{{ $item->opening_time ? \Carbon\Carbon::parse($item->opening_time)->format('H:i') : '—' }}
                                    </td>
                                    <td>{{ $item->closing_time ? \Carbon\Carbon::parse($item->closing_time)->format('H:i') : '—' }}
                                    </td>
                                    <td>
                                    {{ $item->available_on ? \Carbon\Carbon::parse($item->available_on)->format('Y-m-d') : '—' }}
                                </td>


                                    <td>
                                        <a href="{{ route('hatcheries.edit', $item->id) }}"
                                            class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                        <form action="{{ route('hatcheries.destroy', $item->id) }}" method="POST"
                                            class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger"><i
                                                    class="fas fa-trash"></i></button>
                                        </form>
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
    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/sweetalert.js') }}">
    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/dataTables.min.js') }}">
    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/bootstrap4.min.js') }}">

    {{-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script> --}}

    <script>
        $(document).ready(function() {
            $('#data-table').DataTable({
                responsive: true,
                pageLength: 10,
                order: [
                    [0, 'asc']
                ],
                columnDefs: [{
                    targets: -1,
                    orderable: false,
                    className: 'text-center'
                }],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control form-control-sm');
                }
            });

            // Delete confirmation
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
@endpush -->
