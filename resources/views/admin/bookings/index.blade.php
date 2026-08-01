@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-truck mr-2"></i>Bookings
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin') }}">
                            <i class="fas fa-home mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="breadcrumb-item active">Bookings</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">Bookings Management</h4>
                    <div class="d-flex align-items-center">
                        <!-- Inactive Driver Alert Bell -->
                        <div class="inactive-driver-bell mr-3" id="inactiveDriverBell">
                            <div class="bell-wrapper" onclick="toggleInactiveDropdown()">
                                <i class="fas fa-bell"></i>
                                <span class="bell-badge" id="inactiveBadge">0</span>
                            </div>
                            <div class="inactive-dropdown" id="inactiveDropdown" onclick="event.stopPropagation();">
                                <div class="inactive-dropdown-header">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Inactive Drivers
                                </div>
                                <div class="inactive-dropdown-body" id="inactiveDriverList">
                                    <p class="text-muted text-center mb-0 py-3">No inactive drivers</p>
                                </div>
                                <div class="inactive-dropdown-footer" id="inactiveFooter" style="display: none;"></div>
                            </div>
                        </div>

                        <div class="mr-3">
                            <select id="sourceFilter" class="form-control form-control-sm" style="min-width: 180px;">
                                <option value="all">All Sources</option>
                                <option value="hatchery">Hatchery</option>
                                <option value="spot">Spot Hatchery</option>
                                <option value="vehicle">Vehicle Availability</option>
                            </select>
                        </div>
                        @permission('bookings.create')
                        <a href="{{ route('bookings.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus-circle mr-1"></i> Add Booking
                        </a>
                        @endpermission
                    </div>
                </div>

                <!-- Status Tabs -->
                <ul class="nav nav-tabs nav-tabs-custom mb-4" id="bookingTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="all-tab" data-toggle="tab" href="#all" role="tab" data-filter="all">
                            <i class="fas fa-list mr-1"></i> All
                            <span class="badge status-all ml-1">{{ $bookings->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="pending-tab" data-toggle="tab" href="#pending" role="tab" data-filter="pending">
                            <i class="fas fa-clock mr-1"></i> Pending
                            <span class="badge status-pending ml-1">{{ $bookings->where('status', 1)->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="confirmed-tab" data-toggle="tab" href="#confirmed" role="tab" data-filter="confirmed">
                            <i class="fas fa-check mr-1"></i> Confirmed
                            <span class="badge status-confirmed ml-1">{{ $bookings->where('status', 2)->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="assigned-tab" data-toggle="tab" href="#assigned" role="tab" data-filter="assigned">
                            <i class="fas fa-user-check mr-1"></i> Driver Assigned
                            <span class="badge status-assigned ml-1">{{ $bookings->where('status', 3)->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="inprogress-tab" data-toggle="tab" href="#inprogress" role="tab" data-filter="inprogress">
                            <i class="fas fa-spinner mr-1"></i> In Journey
                            <span class="badge status-inprogress ml-1">{{ $bookings->where('status', 4)->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="completed-tab" data-toggle="tab" href="#completed" role="tab" data-filter="completed">
                            <i class="fas fa-check-circle mr-1"></i> Delivered
                            <span class="badge status-completed ml-1">{{ $bookings->where('status', 5)->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="cancelled-tab" data-toggle="tab" href="#cancelled" role="tab" data-filter="cancelled">
                            <i class="fas fa-times-circle mr-1"></i> Cancelled
                            <span class="badge status-cancelled ml-1">{{ $bookings->where('status', 6)->count() }}</span>
                        </a>
                    </li>
                </ul>

                <div class="table-responsive">
                    <table id="data-table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Booking UID</th>
                                <th>Customer</th>
                                <th>Hatchery</th>
                                <th>Vendor</th>
                                <th>Driver</th>
                                <th>Vehicle</th>
                                <th>Pieces</th>
                                <th>Packing Date</th>
                                <th>Delivery Date</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Drop Location</th>
                                <th>Rating</th>
                                <th>Source</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bookings as $booking)
                                @php
                                    $statusMap = [
                                        1 => ['Pending', 'status-pending', 'pending'],
                                        2 => ['Confirmed', 'status-confirmed', 'confirmed'],
                                        3 => ['Driver Assigned', 'status-assigned', 'assigned'],
                                        4 => ['In Journey', 'status-inprogress', 'inprogress'],
                                        5 => ['Delivered', 'status-completed', 'completed'],
                                        6 => ['Cancelled', 'status-cancelled', 'cancelled'],
                                    ];
                                    $status = $booking->status ?? 1;
                                    [$label, $color, $filterGroup] = $statusMap[$status];

                                    $sourceMap = [
                                        0 => ['Hatchery', 'primary', 'hatchery'],
                                        1 => ['Spot Hatchery', 'warning', 'spot'],
                                        2 => ['Vehicle Availability', 'info', 'vehicle'],
                                    ];
                                    $isSpot = $booking->is_spot ?? 0;
                                    [$sourceLabel, $sourceColor, $sourceFilter] = $sourceMap[$isSpot] ?? $sourceMap[0];
                                @endphp
                                <tr data-status="{{ $filterGroup }}" data-source="{{ $sourceFilter }}">
                                    <td class="font-weight-bold text-primary" data-order="{{ $booking->id }}">#{{ $booking->id }}</td>
                                    <td><span class="badge badge-light">{{ $booking->booking_uid ?? 'N/A' }}</span></td>
                                    <td>
                                        <div>{{ $booking->customer_name }}</div>
                                        <small class="text-muted">{{ $booking->customer_mobile }}</small>
                                    </td>
                                    <td>
                                        <div>{{ $booking->hatchery_name }}</div>
                                        <small class="text-muted">{{ $booking->delivery_location }}</small>
                                    </td>
                                    <td>{{ $booking->vendor->name ?? '-' }}</td>
                                    <td>
                                        <div>{{ $booking->driver_name ?? '-' }}</div>
                                        <small class="text-muted">{{ $booking->driver_mobile }}</small>
                                    </td>
                                    <td>
                                        <div>{{ $booking->vehicle_number ?? '-' }}</div>
                                        <small class="text-muted">{{ $booking->vehicle_started_date ? \Carbon\Carbon::parse($booking->vehicle_started_date)->format('d M, Y') : '' }}</small>
                                    </td>
                                    <td>{{ $booking->no_of_pieces ?? 0 }}</td>
                                    @php
                                        $packingDate = $booking->packing_date
                                            ? \Carbon\Carbon::parse($booking->packing_date)
                                            : null;
                                        $deliveryDate = $booking->delivery_date
                                            ?? $booking->delivery_datetime
                                            ?? $booking->delivery_expected;
                                        $deliveryDate = $deliveryDate
                                            ? \Carbon\Carbon::parse($deliveryDate)
                                            : null;
                                    @endphp
                                    <td data-order="{{ $packingDate?->format('Y-m-d') ?? '' }}">
                                        {{ $packingDate?->format('d M, Y') ?? '-' }}
                                    </td>
                                    <td data-order="{{ $deliveryDate?->format('Y-m-d') ?? '' }}">
                                        {{ $deliveryDate?->format('d M, Y') ?? '-' }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $color }}">{{ $label }}</span>
                                        @if ($status == 4)
                                            <br>
                                            <a href="{{ route('bookings.tracking', $booking->id) }}" class="btn btn-sm btn-outline-primary mt-1" style="font-size: 11px; padding: 2px 8px;">
                                                <i class="fas fa-map-marker-alt mr-1"></i>Track Vehicle
                                            </a>
                                        @endif
                                    </td>
                                    <td>
                                        @if($booking->priority)
                                            <span class="badge badge-dark">{{ $booking->priority }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="drop-location-cell">
                                        @php($dropLoc = $booking->dropping_location ?? $booking->delivery_location ?? '-')
                                        <div title="{{ $dropLoc }}">{{ $dropLoc }}</div>
                                    </td>
                                    <td>
                                        @if($booking->rating)
                                            <span style="color: #f5a623; font-size: 13px;" title="{{ $booking->rating->message }}">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <i class="fas fa-star" style="{{ $i <= $booking->rating->rating ? 'color: #f5a623;' : 'color: #ddd;' }}"></i>
                                                @endfor
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $sourceColor }}">{{ $sourceLabel }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('bookings.show', $booking->id) }}" class="btn btn-info btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @permission('bookings.update')
                                        <a href="{{ route('bookings.edit', $booking->id) }}" class="btn btn-primary btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endpermission
                                        @permission('bookings.delete')
                                        <form action="{{ route('bookings.destroy', $booking->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm delete-btn" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        @endpermission
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

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin_assets/ravindra/js/bootstrap4.min.css') }}">
    <style>
        .dataTables_wrapper .dataTables_filter input {
            margin-left: 0.5em;
            display: inline-block;
            width: auto;
        }
        .nav-tabs-custom .nav-link {
            border: none;
            padding: 10px 20px;
            color: #6c757d;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        .nav-tabs-custom .nav-link:hover {
            color: #495057;
            border-bottom-color: #dee2e6;
        }
        .nav-tabs-custom .nav-link.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background: transparent;
        }
        .nav-tabs-custom {
            border-bottom: 1px solid #dee2e6;
        }
        .badge {
            font-size: 11px;
        }
        /* Show full dropping address — wrap instead of truncating */
        .drop-location-cell {
            min-width: 220px;
            max-width: 280px;
            white-space: normal !important;
            word-break: break-word;
            line-height: 1.4;
        }
        .drop-location-cell > div {
            white-space: normal !important;
            word-break: break-word;
        }
        .badge.status-all {
            background-color: #465352;
            color: #fff;
        }
        .badge.status-pending {
            background-color: #FFC107;
            color: #000;
        }
        .badge.status-confirmed {
            background-color: #6f42c1;
            color: #fff;
        }
        .badge.status-assigned {
            background-color: #20c997;
            color: #fff;
        }
        .badge.status-inprogress {
            background-color: #007bff;
            color: #fff;
        }
        .badge.status-completed {
            background-color: #28a745;
            color: #fff;
        }
        .badge.status-cancelled {
            background-color: #dc3545;
            color: #fff;
        }

        /* Inactive Driver Bell */
        .inactive-driver-bell {
            position: relative;
        }
        .bell-wrapper {
            cursor: pointer;
            position: relative;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #fff3cd;
            border: 2px solid #ffc107;
            transition: all 0.3s ease;
        }
        .bell-wrapper:hover {
            background: #ffc107;
        }
        .bell-wrapper:hover i {
            color: #fff;
        }
        .bell-wrapper i {
            font-size: 18px;
            color: #d39e00;
            animation: bellShake 2s infinite;
        }
        .bell-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #dc3545;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
        }
        @keyframes bellShake {
            0%, 100% { transform: rotate(0); }
            5% { transform: rotate(15deg); }
            10% { transform: rotate(-15deg); }
            15% { transform: rotate(10deg); }
            20% { transform: rotate(-10deg); }
            25% { transform: rotate(0); }
        }

        /* Inactive Dropdown */
        .inactive-dropdown {
            display: none;
            position: absolute;
            top: 48px;
            right: 0;
            width: 380px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.15);
            z-index: 1050;
            border: 1px solid #e0e0e0;
            overflow: hidden;
        }
        .inactive-dropdown.show {
            display: block;
        }
        .inactive-dropdown-header {
            background: #dc3545;
            color: #fff;
            padding: 10px 16px;
            font-weight: 600;
            font-size: 14px;
        }
        .inactive-dropdown-body {
            max-height: 350px;
            overflow-y: auto;
        }
        .inactive-driver-item {
            padding: 12px 16px;
            margin: 8px 10px;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
            background: #fff;
            transition: background 0.2s;
        }
        .inactive-driver-item:hover {
            background: #f8f9fa;
        }
        .inactive-driver-item.read {
            background: #f5f5f5;
        }
        .inactive-driver-item .driver-name {
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }
        .inactive-driver-item .driver-meta {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
        .inactive-driver-item .inactive-time {
            display: inline-block;
            background: #dc3545;
            color: #fff;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 4px;
        }
        .inactive-driver-item .track-btn {
            font-size: 11px;
            padding: 2px 10px;
            margin-top: 4px;
            margin-left: 6px;
        }
        .inactive-dropdown-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #666;
        }
        .inactive-dropdown-footer a {
            color: #007bff;
            font-weight: 600;
            text-decoration: none;
            font-size: 13px;
        }
        .inactive-dropdown-footer a:hover {
            text-decoration: underline;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('admin_assets/ravindra/js/dataTables.min.js') }}"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            // Custom filter function for status tabs and source filter
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                var statusFilter = $('#bookingTabs .nav-link.active').data('filter');
                var sourceFilter = $('#sourceFilter').val();
                var row = $(settings.aoData[dataIndex].nTr);
                var rowStatus = row.data('status');
                var rowSource = row.data('source');

                var statusMatch = (statusFilter === 'all' || rowStatus === statusFilter);
                var sourceMatch = (sourceFilter === 'all' || rowSource === sourceFilter);

                return statusMatch && sourceMatch;
            });

            // Initialize DataTable
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
                    searchPlaceholder: "Search bookings...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ bookings",
                    emptyTable: "No bookings found",
                    infoFiltered: "(filtered from _MAX_ total)"
                },
                columnDefs: [{
                    targets: -1,
                    orderable: false
                }],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
            });

            // Tab click handler - redraw table with filter
            $('#bookingTabs .nav-link').on('click', function(e) {
                e.preventDefault();
                $('#bookingTabs .nav-link').removeClass('active');
                $(this).addClass('active');
                table.draw();
            });

            // Source filter change handler
            $('#sourceFilter').on('change', function() {
                table.draw();
            });

            // Delete confirmation (AJAX - stays on same page)
            $(document).on('click', '.delete-btn', function(e) {
                e.preventDefault();
                let form = $(this).closest('form');
                let row = $(this).closest('tr');

                Swal.fire({
                    title: 'Are you sure?',
                    text: 'This booking will be permanently deleted!',
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

            // Success toast
            @if (session('success'))
                Swal.fire({
                    toast: true,
                    position: 'top-right',
                    icon: 'success',
                    title: "{{ addslashes(session('success')) }}",
                    showConfirmButton: false,
                    timer: 3000
                });
            @endif

            // Error toast
            @if (session('error'))
                Swal.fire({
                    toast: true,
                    position: 'top-right',
                    icon: 'error',
                    title: "{{ addslashes(session('error')) }}",
                    showConfirmButton: false,
                    timer: 3000
                });
            @endif

            // Inactive driver alerts
            fetchInactiveDrivers();
            setInterval(fetchInactiveDrivers, 15000); // every 15 seconds for near-real-time alerts
        });

        var _lastReadHash = localStorage.getItem('_inactiveReadHash') || '';
        var _bellRead = localStorage.getItem('_inactiveBellRead') === 'true';
        var _inactivePage = 1;
        var _hasMore = false;
        var _loadingMore = false;
        var _totalInactive = 0;

        function fetchInactiveDrivers() {
            // Don't refresh while dropdown is open (user is reading/scrolling)
            if ($('#inactiveDropdown').hasClass('show')) return;

            _inactivePage = 1;
            $.get('{{ route("bookings.inactive-drivers") }}', { page: 1 }, function(data) {
                var bell = $('#inactiveDriverBell');
                var badge = $('#inactiveBadge');
                var list = $('#inactiveDriverList');

                _totalInactive = data.count;
                _hasMore = data.has_more;

                if (data.count > 0) {
                    bell.show();

                    // Build hash from booking IDs to detect actual changes
                    var currentHash = data.drivers.map(function(d) { return d.booking_id; }).sort().join(',');

                    // Clean up read IDs — remove ones no longer inactive
                    var currentIds = data.drivers.map(function(d) { return String(d.booking_id); });
                    var readIds = JSON.parse(localStorage.getItem('_inactiveReadIds') || '[]');
                    var cleanedIds = readIds.filter(function(id) { return currentIds.indexOf(id) !== -1; });
                    localStorage.setItem('_inactiveReadIds', JSON.stringify(cleanedIds));

                    if (!_bellRead || currentHash !== _lastReadHash) {
                        badge.text(data.count).show();
                        $('.bell-wrapper i').css('animation', 'bellShake 2s infinite');
                        if (currentHash !== _lastReadHash) {
                            _bellRead = false;
                            localStorage.setItem('_inactiveBellRead', 'false');
                        }
                    }

                    var html = buildDriverItems(data.drivers);
                    if (_hasMore) {
                        html += '<div class="inactive-scroll-loader text-center py-2" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
                    }
                    list.html(html);
                    updateFooter(data.drivers.length, data.count);
                } else {
                    bell.show();
                    badge.text('0').show();
                    $('.bell-wrapper i').css('animation', 'none');
                    list.html('<p class="text-muted text-center mb-0 py-3">No inactive drivers</p>');
                    $('#inactiveFooter').hide();
                    _bellRead = true;
                    _lastReadHash = '';
                }
            });
        }

        function loadMoreInactive() {
            if (_loadingMore || !_hasMore) return;
            _loadingMore = true;
            _inactivePage++;
            $('.inactive-scroll-loader').show();

            $.get('{{ route("bookings.inactive-drivers") }}', { page: _inactivePage }, function(data) {
                _hasMore = data.has_more;
                var list = $('#inactiveDriverList');

                // Remove old loader
                list.find('.inactive-scroll-loader').remove();

                // Append new items
                var html = buildDriverItems(data.drivers);
                if (_hasMore) {
                    html += '<div class="inactive-scroll-loader text-center py-2" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
                }

                list.append(html);

                var loadedCount = list.find('.inactive-driver-item').length;
                updateFooter(loadedCount, _totalInactive);
                _loadingMore = false;
            });
        }

        function buildDriverItems(drivers) {
            var html = '';
            drivers.forEach(function(d) {
                var timeLabel = '';
                var totalMins = d.minutes_inactive;
                if (totalMins >= 1440) {
                    var days = Math.floor(totalMins / 1440);
                    var hrs = Math.floor((totalMins % 1440) / 60);
                    var mins = totalMins % 60;
                    timeLabel = days + 'd ' + hrs + 'h ' + mins + 'm inactive';
                } else if (totalMins >= 60) {
                    var hrs = Math.floor(totalMins / 60);
                    var mins = totalMins % 60;
                    timeLabel = hrs + 'h ' + mins + 'm inactive';
                } else {
                    timeLabel = totalMins + ' min inactive';
                }

                var readIds = JSON.parse(localStorage.getItem('_inactiveReadIds') || '[]');
                var isRead = readIds.indexOf(String(d.booking_id)) !== -1;
                html += '<div class="inactive-driver-item' + (isRead ? ' read' : '') + '">';
                html += '<div class="d-flex justify-content-between align-items-start">';
                html += '<div>';
                html += '<div class="driver-name"><i class="fas fa-user mr-1"></i> ' + d.driver_name + '</div>';
                html += '<div class="driver-meta"><i class="fas fa-phone mr-1"></i> ' + d.driver_mobile + ' &nbsp; <i class="fas fa-truck mr-1"></i> ' + d.vehicle_number + '</div>';
                html += '<div class="driver-meta"><i class="fas fa-box mr-1"></i> Booking #' + d.booking_id + ' &nbsp; <i class="fas fa-user-tag mr-1"></i> ' + d.customer_name + '</div>';
                html += '<div class="driver-meta"><i class="fas fa-map-pin mr-1"></i> Last: ' + d.driver_location_name + '</div>';
                html += '<div class="driver-meta"><i class="fas fa-clock mr-1"></i> Last update: ' + d.last_updated + '</div>';
                html += '</div>';
                html += '</div>';
                html += '<div class="mt-1">';
                var reasonBadge = d.reason ? '<span class="badge badge-dark mr-1" style="font-size:10px;">' + d.reason + '</span>' : '';
                html += reasonBadge;
                html += '<span class="inactive-time"><i class="fas fa-exclamation-circle mr-1"></i> ' + timeLabel + '</span>';
                html += '<a href="/admin/bookings/' + d.booking_id + '/tracking" class="btn btn-sm btn-outline-primary track-btn"><i class="fas fa-map-marker-alt mr-1"></i>Track</a>';
                html += '</div>';
                html += '</div>';
            });
            return html;
        }

        function updateFooter(loadedCount, totalCount) {
            var footer = $('#inactiveFooter');
            if (loadedCount >= totalCount) {
                footer.hide();
                return;
            }
            footer.html('<span>Showing ' + loadedCount + ' of ' + totalCount + ' inactive</span>' +
                '<a href="javascript:void(0)" onclick="event.stopPropagation(); loadAllInactive();">View All <i class="fas fa-arrow-right ml-1"></i></a>');
            footer.show();
        }

        function toggleInactiveDropdown() {
            var dropdown = $('#inactiveDropdown');
            dropdown.toggleClass('show');

            if (dropdown.hasClass('show')) {
                _bellRead = true;
                // Save current driver IDs as read hash
                var ids = [];
                $('#inactiveDriverList .inactive-driver-item').each(function() {
                    var text = $(this).text();
                    var match = text.match(/Booking #(\d+)/);
                    if (match) ids.push(match[1]);
                });
                _lastReadHash = ids.sort().join(',');
                localStorage.setItem('_inactiveBellRead', 'true');
                localStorage.setItem('_inactiveReadHash', _lastReadHash);
                // Save read IDs for grey background
                var existingReadIds = JSON.parse(localStorage.getItem('_inactiveReadIds') || '[]');
                ids.forEach(function(id) { if (existingReadIds.indexOf(id) === -1) existingReadIds.push(id); });
                localStorage.setItem('_inactiveReadIds', JSON.stringify(existingReadIds));
                $('#inactiveBadge').hide();
                $('.bell-wrapper i').css('animation', 'none');

                // Bind scroll event for infinite scroll
                $('#inactiveDriverList').off('scroll.inactive').on('scroll.inactive', function() {
                    if (this.scrollTop + this.clientHeight >= this.scrollHeight - 20) {
                        loadMoreInactive();
                    }
                });
            }
        }

        // Load ALL remaining inactive drivers at once
        function loadAllInactive() {
            if (_loadingMore) return;
            _loadingMore = true;

            $('#inactiveFooter').html('<span><i class="fas fa-spinner fa-spin mr-1"></i> Loading all...</span>');

            $.get('{{ route("bookings.inactive-drivers") }}', { page: 'all' }, function(data) {
                var list = $('#inactiveDriverList');
                list.html(buildDriverItems(data.drivers));
                _hasMore = false;
                _loadingMore = false;
                $('#inactiveFooter').hide();
            });
        }

        // Close dropdown on outside click
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.inactive-driver-bell').length) {
                $('#inactiveDropdown').removeClass('show');
            }
        });
    </script>
@endpush
