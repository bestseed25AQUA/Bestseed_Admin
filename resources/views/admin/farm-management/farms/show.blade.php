@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-fish mr-2"></i>{{ $farm->farm_name }}
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('farm-management.farms.index') }}">Farms</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $farm->farm_name }}</li>
                </ol>
            </nav>
        </div>

        {{-- Summary strip --}}
        <div class="row">
            @php
                $cards = [
                    ['Owner', $farm->farmer ? (trim($farm->farmer->first_name . ' ' . $farm->farmer->last_name) ?: 'Farmer #' . $farm->farmer->id) : 'Missing', 'fa-user', 'primary'],
                    // Every tank the farm has, DELETED ones included.
                    //
                    // It counted only the live ones, so a farm with five tanks
                    // and one deleted read "5" while the list below it offered
                    // a "Deleted tanks (1)" section — the page disagreed with
                    // itself about how many tanks existed.
                    //
                    // The deleted count is named rather than left implied: at
                    // "6 (5 active)" alone a reader assumes the sixth is
                    // inactive, which is a different thing entirely.
                    [
                        'Tanks',
                        ($tanks->count() + $deletedTanks->count())
                            . ' (' . $tanks->where('status', 1)->count() . ' active'
                            . ($deletedTanks->count()
                                ? ', ' . $deletedTanks->count() . ' deleted'
                                : '')
                            . ')',
                        'fa-cubes',
                        'info',
                    ],
                    ['Team', $team->where('is_partner', 0)->count() . ' managers, ' . $team->where('is_partner', 1)->count() . ' partners', 'fa-users', 'success'],
                    ['Feed Used', (float) $totalFeedUsed, 'fa-chart-line', 'warning'],
                ];
            @endphp
            @foreach ($cards as [$label, $value, $icon, $colour])
                {{-- h-100 on the card, and the column as a flex box for it to
                     fill. Without it each card is only as tall as its own text,
                     so "0 managers, 0 partners" wrapping to two lines left that
                     one card taller than the three beside it and the strip
                     looked ragged along the bottom. --}}
                <div class="col-md-3 mb-3 d-flex">
                    <div class="card h-100 w-100">
                        <div class="card-body d-flex align-items-center">
                            {{-- Fixed width, so the labels start on the same
                                 x-position in every card however wide the glyph
                                 happens to be. --}}
                            <i class="fas {{ $icon }} fa-2x text-{{ $colour }} mr-3 text-center"
                               style="width:34px; flex:0 0 34px;"></i>
                            {{-- min-width:0 lets a long value wrap inside the flex row instead of
                                 pushing the card wider. Bootstrap 4 has no min-w-0 utility. --}}
                            <div style="min-width:0;">
                                <small class="text-muted d-block">{{ $label }}</small>
                                <strong>{{ $value }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <ul class="nav nav-tabs card-header-tabs border-0" id="farmTabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-details">Details</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-tanks">Tanks ({{ $tanks->count() }})</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-team">Managers &amp; Partners ({{ $team->count() }})</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-members">Who Has Access ({{ $members->count() }})</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-activity">History ({{ $activity->count() }})</a></li>
                    </ul>

                    @permission('farm-management.update')
                        <a href="{{ route('farm-management.farms.edit', $farm->id) }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit mr-1"></i> Edit Farm
                        </a>
                    @endpermission
                </div>

                <div class="tab-content pt-3">
                    {{-- ---------------------------------------------------- Details --}}
                    <div class="tab-pane fade show active" id="tab-details">
                        {{-- colgroup + fixed layout, so the label column is
                             exactly 220px on every row. width on a single <th>
                             is only a hint under auto layout: the browser still
                             sizes the column to the widest label, so the gap
                             between labels and values drifted. --}}
                        <table class="table table-sm farm-details">
                            <colgroup>
                                <col style="width:220px">
                                <col>
                            </colgroup>
                            <tr><th>Farm ID</th><td>{{ $farm->id }}</td></tr>
                            <tr><th>Farm Name</th><td>{{ $farm->farm_name }}</td></tr>
                            <tr>
                                <th>Owner</th>
                                <td>
                                    @if ($farm->farmer)
                                        {{ trim($farm->farmer->first_name . ' ' . $farm->farmer->last_name) }}
                                        &middot; {{ $farm->farmer->mobile }}
                                        <span class="badge bg-secondary ml-1">Farmer #{{ $farm->farmer->id }}</span>
                                    @else
                                        <span class="text-danger">Owner record missing (farmer_id {{ $farm->farmer_id }})</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    @if ($farm->trashed())
                                        <span class="badge bg-dark">Deleted</span>
                                        <small class="text-muted ml-2">Hidden from the app. Restore it from the farms list.</small>
                                    @elseif ($farm->status)
                                        <span class="badge bg-success">Active</span>
                                        <small class="text-muted ml-2">Visible in the app to the owner and anyone with access.</small>
                                    @else
                                        <span class="badge bg-warning">Inactive</span>
                                        <small class="text-muted ml-2">Hidden from the app; all data is intact.</small>
                                    @endif
                                </td>
                            </tr>
                            <tr><th>Stocking Date</th><td>{{ $farm->stocking_date ? date('d-m-Y', strtotime($farm->stocking_date)) : '-' }}</td></tr>
                            <tr><th>Declared Tanks</th><td>{{ $farm->no_of_tanks ?? '-' }}</td></tr>
                            <tr><th>Store</th><td>{{ $farm->store ?? '-' }}</td></tr>
                            <tr><th>Low Feed Limit</th><td>{{ $farm->low_feed_limit ?? '-' }}</td></tr>
                            <tr><th>Total Feed Used</th><td>{{ (float) $totalFeedUsed }}</td></tr>
                            <tr><th>Created</th><td>{{ optional($farm->created_at)->format('d-m-Y, h:i A') ?? '-' }}</td></tr>
                        </table>
                    </div>

                    {{-- ------------------------------------------------------ Tanks --}}
                    <div class="tab-pane fade" id="tab-tanks">
                        @permission('farm-management.create')
                            <button class="btn btn-sm btn-primary mb-3" data-toggle="collapse" data-target="#addTank">
                                <i class="fas fa-plus mr-1"></i> Add Tank
                            </button>

                            <div class="collapse mb-4" id="addTank">
                                <div class="card card-body bg-light">
                                    <form action="{{ route('farm-management.tanks.store', $farm->id) }}" method="POST">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-3 form-group">
                                                <label>Tank Name</label>
                                                <input type="text" name="tank_name" class="form-control" required>
                                            </div>
                                            <div class="col-md-2 form-group">
                                                <label>Status</label>
                                                <select name="status" class="form-control">
                                                    <option value="1">Active</option>
                                                    <option value="0">Inactive</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2 form-group">
                                                <label>Meals / day</label>
                                                <input type="number" name="meals" class="form-control" min="0">
                                            </div>
                                            <div class="col-md-2 form-group">
                                                <label>Store</label>
                                                <input type="number" step="0.01" name="store" class="form-control" min="0">
                                            </div>
                                            <div class="col-md-3 form-group">
                                                <label>Stocking Date</label>
                                                <input type="date" name="stocking_date" class="form-control"
                                                    value="{{ $farm->stocking_date ? \Illuminate\Support\Carbon::parse($farm->stocking_date)->format('Y-m-d') : '' }}">
                                                <small class="text-muted">Defaults to the farm's stocking date.</small>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus mr-1"></i> Add Tank
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endpermission

                        @if ($tanks->isEmpty())
                            <p class="text-muted mb-0">No tanks recorded for this farm yet.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th><th>Tank</th><th>Status</th><th>Meals</th>
                                            <th>Store</th><th>Feed Used</th><th>Stocking Date</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($tanks as $tank)
                                            <tr>
                                                <td>{{ $tank->id }}</td>
                                                <td>{{ $tank->tank_name }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $tank->status ? 'success' : 'secondary' }}">
                                                        {{ $tank->status ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </td>
                                                <td>{{ $tank->meals ?? '-' }}</td>
                                                <td>{{ $tank->store ?? '-' }}</td>
                                                {{-- The crop CURRENTLY in the tank, not the
                                                     tank's lifetime. `total_feed_used` on the row
                                                     accumulates across batches, so a re-stocked
                                                     tank showed the harvested crop's feed added to
                                                     the new one's. Set in FarmManagementController::show. --}}
                                                <td>{{ number_format($tank->current_batch_feed ?? 0, 2) }}</td>
                                                <td>{{ $tank->stocking_date ? date('d-m-Y', strtotime($tank->stocking_date)) : '-' }}</td>
                                                <td class="text-center text-nowrap">
                                                    @permission('farm-management.view')
                                                        <a href="{{ route('farm-management.tanks.feed', [$farm->id, $tank->id]) }}"
                                                            class="btn btn-sm btn-info btn-action" title="Feed records">
                                                            <i class="fas fa-utensils"></i>
                                                        </a>
                                                    @endpermission

                                                    @permission('farm-management.update')
                                                        <button class="btn btn-sm btn-primary btn-action" title="Edit tank"
                                                            data-toggle="collapse" data-target="#editTank{{ $tank->id }}">
                                                            <i class="fas fa-edit"></i>
                                                        </button>

                                                        {{-- Harvesting needs no answers, so it stays a
                                                             single click. Starting a crop needs two, so
                                                             it opens the row below instead of firing
                                                             straight off. --}}
                                                        @if ($tank->status)
                                                            <form action="{{ route('farm-management.tanks.toggle-status', [$farm->id, $tank->id]) }}"
                                                                method="POST" class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-warning btn-action"
                                                                    title="Deactivate">
                                                                    <i class="fas fa-power-off"></i>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <button class="btn btn-sm btn-success btn-action" title="Activate"
                                                                data-toggle="collapse" data-target="#startCrop{{ $tank->id }}">
                                                                <i class="fas fa-power-off"></i>
                                                            </button>
                                                        @endif
                                                    @endpermission

                                                    @permission('farm-management.delete')
                                                        <form action="{{ route('farm-management.tanks.destroy', [$farm->id, $tank->id]) }}"
                                                            method="POST" class="d-inline confirm-form">
                                                            @csrf @method('DELETE')
                                                            <button type="button" class="btn btn-sm btn-danger btn-action confirm-btn"
                                                                data-confirm="The tank is hidden from the farmer's app and its crop is closed. Its feed records are kept, and you can restore it from the Deleted tanks list below.">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endpermission
                                                </td>
                                            </tr>

                                            @permission('farm-management.update')
                                                <tr class="collapse" id="editTank{{ $tank->id }}">
                                                    <td colspan="7" class="bg-light">
                                                        <form action="{{ route('farm-management.tanks.update', [$farm->id, $tank->id]) }}" method="POST">
                                                            @csrf @method('PUT')
                                                            <div class="row">
                                                                <div class="col-md-3 form-group mb-2">
                                                                    <label class="small mb-1">Tank Name</label>
                                                                    <input type="text" name="tank_name" class="form-control form-control-sm"
                                                                        value="{{ $tank->tank_name }}" required>
                                                                </div>
                                                                <div class="col-md-2 form-group mb-2">
                                                                    <label class="small mb-1">Status</label>
                                                                    <select name="status" class="form-control form-control-sm">
                                                                        <option value="1" @selected($tank->status)>Active</option>
                                                                        <option value="0" @selected(!$tank->status)>Inactive</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-2 form-group mb-2">
                                                                    <label class="small mb-1">Meals / day</label>
                                                                    <input type="number" name="meals" class="form-control form-control-sm"
                                                                        min="0" value="{{ $tank->meals }}">
                                                                </div>
                                                                <div class="col-md-2 form-group mb-2">
                                                                    <label class="small mb-1">Store</label>
                                                                    <input type="number" step="0.01" name="store" class="form-control form-control-sm"
                                                                        min="0" value="{{ $tank->store }}">
                                                                </div>
                                                                <div class="col-md-3 form-group mb-2">
                                                                    <label class="small mb-1">Stocking Date</label>
                                                                    <input type="date" name="stocking_date" class="form-control form-control-sm"
                                                                        value="{{ $tank->stocking_date ? \Illuminate\Support\Carbon::parse($tank->stocking_date)->format('Y-m-d') : '' }}">
                                                                </div>
                                                            </div>
                                                            <button type="submit" class="btn btn-sm btn-primary mb-2">
                                                                <i class="fas fa-save mr-1"></i> Save Tank
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>

                                                {{-- Starting a crop, with the two answers the app asks
                                                     for on the same action.

                                                     Activating a tank begins a NEW crop, so it needs a
                                                     date to count days from — firing straight off made
                                                     a pond stocked a fortnight ago read as Day 1 — and,
                                                     when that date is past, whatever it was already
                                                     fed, so its history is not simply missing. --}}
                                                @unless ($tank->status)
                                                    <tr class="collapse" id="startCrop{{ $tank->id }}">
                                                        <td colspan="7" class="bg-light">
                                                            <form action="{{ route('farm-management.tanks.toggle-status', [$farm->id, $tank->id]) }}"
                                                                method="POST">
                                                                @csrf
                                                                <div class="row align-items-end">
                                                                    <div class="col-md-3 form-group mb-2">
                                                                        <label class="small mb-1">
                                                                            Stocking date <span class="text-danger">*</span>
                                                                        </label>
                                                                        <input type="date" name="stocking_date" required
                                                                            class="form-control form-control-sm crop-date"
                                                                            {{-- A crop cannot have been stocked
                                                                                 on a day that has not happened. --}}
                                                                            max="{{ now()->toDateString() }}"
                                                                            value="{{ now()->toDateString() }}">
                                                                    </div>

                                                                    {{-- Hidden until the date is actually in the
                                                                         past. It opened on today with this box
                                                                         already showing, asking for "feed already
                                                                         used" on a crop that starts in an hour —
                                                                         a question with no possible answer. The
                                                                         date decides whether it is asked at all. --}}
                                                                    <div class="col-md-3 form-group mb-2 crop-feed-wrap"
                                                                         style="display:none;">
                                                                        <label class="small mb-1 crop-feed-label">Feed already used (kg)</label>
                                                                        <input type="number" step="0.01" min="0"
                                                                            name="feed_used_before"
                                                                            class="form-control form-control-sm crop-feed"
                                                                            placeholder="0">
                                                                    </div>
                                                                    <div class="col-md-6 form-group mb-2">
                                                                        <button type="submit" class="btn btn-sm btn-success">
                                                                            <i class="fas fa-power-off mr-1"></i> Start Crop
                                                                        </button>
                                                                        <span class="small text-muted ml-2 crop-hint">
                                                                            Pick the day the crop went in. Choose a
                                                                            past day and you will also be asked what
                                                                            it has already been fed.
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @endunless
                                            @endpermission
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- Deleted tanks.
                             Deleting a tank hides it; its feed records stay put.
                             Collapsed by default so the working list is not
                             cluttered by ponds nobody uses any more, but present
                             so removing the wrong one is not the end of it. --}}
                        @if ($deletedTanks->isNotEmpty())
                            <button class="btn btn-sm btn-outline-secondary mt-4"
                                data-toggle="collapse" data-target="#deletedTanks">
                                <i class="fas fa-trash-restore mr-1"></i>
                                Deleted tanks ({{ $deletedTanks->count() }})
                            </button>

                            <div class="collapse mt-3" id="deletedTanks">
                                <div class="alert alert-light border small mb-2">
                                    These tanks are hidden from the farmer's app. Their feed
                                    records are kept, so restoring one brings its history back
                                    with it. A restored tank comes back <strong>inactive</strong>
                                    — start a crop when it is stocked again.
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Tank</th>
                                                <th>Stocking date</th>
                                                <th>Deleted</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($deletedTanks as $tank)
                                                <tr class="table-secondary">
                                                    <td>{{ $tank->id }}</td>
                                                    <td>{{ $tank->tank_name }}</td>
                                                    <td>
                                                        {{ $tank->stocking_date
                                                            ? \Illuminate\Support\Carbon::parse($tank->stocking_date)->format('d M Y')
                                                            : '—' }}
                                                    </td>
                                                    <td>{{ $tank->deleted_at?->format('d M Y, H:i') ?? '—' }}</td>
                                                    <td class="text-center text-nowrap">
                                                        @permission('farm-management.update')
                                                            <form action="{{ route('farm-management.tanks.restore', [$farm->id, $tank->id]) }}"
                                                                method="POST" class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-success btn-action"
                                                                    title="Restore this tank">
                                                                    <i class="fas fa-undo"></i> Restore
                                                                </button>
                                                            </form>
                                                        @endpermission

                                                        @permission('farm-management.delete')
                                                            {{-- The one there is no way back from, so it only
                                                                 appears for a tank already deleted and says
                                                                 plainly what it destroys. --}}
                                                            <form action="{{ route('farm-management.tanks.force-destroy', [$farm->id, $tank->id]) }}"
                                                                method="POST" class="d-inline confirm-form">
                                                                @csrf @method('DELETE')
                                                                <button type="button" class="btn btn-sm btn-danger btn-action confirm-btn"
                                                                    data-confirm="This erases the tank and every feed record ever logged against it. There is no way back. Leave it deleted instead if you may want it later."
                                                                    title="Remove permanently">
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
                        @endif
                    </div>

                    {{-- ------------------------------------------------------- Team --}}
                    <div class="tab-pane fade" id="tab-team">
                        @permission('farm-management.create')
                            <a href="{{ route('farm-management.team.create', ['farm_id' => $farm->id]) }}"
                                class="btn btn-sm btn-primary mb-3">
                                <i class="fas fa-user-plus mr-1"></i> Add Manager / Partner
                            </a>
                        @endpermission

                        @if ($team->isEmpty())
                            <p class="text-muted mb-0">Nobody has been given access to this farm yet.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th><th>Name</th><th>Phone</th><th>Role</th>
                                            <th>Permissions</th><th>Added</th><th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($team as $member)
                                            <tr>
                                                <td>{{ $member->id }}</td>
                                                <td>{{ $member->name }}</td>
                                                <td>{{ $member->phone }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $member->is_partner ? 'secondary' : 'info' }}">
                                                        {{ ucfirst($member->role_label) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @include('admin.farm-management.partials._permission-badges', ['row' => $member])
                                                </td>
                                                <td>{{ optional($member->created_at)->format('d-m-Y') ?? '-' }}</td>
                                                <td class="text-center">
                                                    @permission('farm-management.update')
                                                        <a href="{{ route('farm-management.team.edit', $member->id) }}"
                                                            class="btn btn-sm btn-primary btn-action" title="Edit access">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    @endpermission
                                                    @permission('farm-management.delete')
                                                        <form action="{{ route('farm-management.team.destroy', $member->id) }}"
                                                            method="POST" class="d-inline confirm-form">
                                                            @csrf @method('DELETE')
                                                            <button type="button" class="btn btn-sm btn-danger btn-action confirm-btn"
                                                                data-confirm="They lose access to this farm immediately.">
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
                        @endif
                    </div>

                    {{-- ------------------------------------------- Who Has Access --}}
                    {{-- A membership IS the access — this is the table the
                         server consults when deciding who may open the farm.
                         People get here by being picked directly, now the only
                         way in; the QR + PIN flow that also used to land people
                         here has been removed. --}}
                    <div class="tab-pane fade" id="tab-members">
                        @permission('farm-management.create')
                            <button class="btn btn-sm btn-primary mb-3" data-toggle="collapse" data-target="#addMembers">
                                <i class="fas fa-user-check mr-1"></i> Give Access Directly
                            </button>

                            <div class="collapse mb-4" id="addMembers">
                                <div class="card card-body bg-light">
                                    <form action="{{ route('farm-management.members.store', $farm->id) }}" method="POST">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-6 form-group">
                                                <label>People</label>
                                                <input type="text" class="form-control mb-2" id="memberFilter"
                                                    placeholder="Type a name or mobile number to narrow the list"
                                                    autocomplete="off">
                                                <select name="farmer_ids[]" id="memberSelect" class="form-control" multiple size="8" required>
                                                    @foreach ($farmers as $person)
                                                        <option value="{{ $person->id }}">
                                                            {{ trim($person->first_name . ' ' . $person->last_name) ?: 'Farmer #' . $person->id }}
                                                            — {{ $person->mobile }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted">
                                                    Hold Ctrl (Cmd on Mac) to pick several. They get access straight
                                                    away.
                                                </small>
                                                <small class="text-muted d-block" id="memberFilterCount"></small>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Role</label>
                                                    <select name="role" class="form-control" required>
                                                        <option value="manager">Manager</option>
                                                        <option value="partner">Partner</option>
                                                    </select>
                                                </div>
                                                <label class="d-block"><strong>What they may do</strong></label>
                                                {{-- Starts as Manager, matching the select above; the
                                                     partial's script re-applies the default if that is
                                                     switched to Partner. --}}
                                                @include('admin.farm-management.partials._permissions', [
                                                    'values' => null,
                                                    'isPartner' => false,
                                                ])
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-primary mt-2">
                                            <i class="fas fa-user-check mr-1"></i> Give Access
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endpermission

                        @if ($members->isEmpty())
                            <p class="text-muted mb-0">
                                Nobody holds access to this farm yet. Give someone access using the button
                                above.
                            </p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th><th>Person</th><th>Role</th><th>How</th>
                                            <th>Permissions</th><th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($members as $member)
                                            @php
                                                $person = $member->farmer;

                                                // The farmer's own name, and what THIS farm calls
                                                // them. The label wins here for the same reason it
                                                // wins in the app: an owner who typed "Ramesh
                                                // (pump shed)" should find that name on both
                                                // screens, not one name here and another there.
                                                $ownName = $person
                                                    ? (trim($person->first_name . ' ' . $person->last_name) ?: 'Farmer #' . $person->id)
                                                    : 'Farmer #' . $member->farmer_id;
                                                $label = trim((string) $member->display_name);
                                            @endphp
                                            <tr>
                                                <td>{{ $member->id }}</td>
                                                <td>
                                                    {{ $label !== '' ? $label : $ownName }}
                                                    <div class="small text-muted">
                                                        {{ optional($person)->mobile }}
                                                        {{-- Shown only when the two differ, so it is
                                                             clear the row is labelled rather than
                                                             the person renamed. --}}
                                                        @if ($label !== '' && $label !== $ownName)
                                                            &middot; registered as {{ $ownName }}
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{ $member->role === 'partner' ? 'secondary' : 'info' }}">
                                                        {{ ucfirst($member->role) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="small">Added directly</span>
                                                    @if ($member->grantedBy)
                                                        <div class="small text-muted">
                                                            by {{ trim($member->grantedBy->first_name . ' ' . $member->grantedBy->last_name) ?: 'Farmer #' . $member->granted_by }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @include('admin.farm-management.partials._permission-badges', ['row' => $member])
                                                </td>
                                                <td>
                                                    @if ($member->revoked_at)
                                                        <span class="badge bg-danger">Revoked</span>
                                                    @else
                                                        <span class="badge bg-success">Active</span>
                                                    @endif
                                                </td>
                                                <td class="text-center text-nowrap">
                                                    @permission('farm-management.update')
                                                        <button class="btn btn-sm btn-primary btn-action" title="Edit access"
                                                            data-toggle="collapse" data-target="#editMember{{ $member->id }}">
                                                            <i class="fas fa-edit"></i>
                                                        </button>

                                                        @if ($member->revoked_at)
                                                            <form action="{{ route('farm-management.members.restore', $member->id) }}"
                                                                method="POST" class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-success btn-action" title="Restore access">
                                                                    <i class="fas fa-undo"></i>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <form action="{{ route('farm-management.members.revoke', $member->id) }}"
                                                                method="POST" class="d-inline confirm-form">
                                                                @csrf
                                                                <button type="button" class="btn btn-sm btn-warning btn-action confirm-btn"
                                                                    data-confirm="They lose access to this farm immediately.">
                                                                    <i class="fas fa-ban"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endpermission

                                                    @permission('farm-management.delete')
                                                        <form action="{{ route('farm-management.members.destroy', $member->id) }}"
                                                            method="POST" class="d-inline confirm-form">
                                                            @csrf @method('DELETE')
                                                            <button type="button" class="btn btn-sm btn-danger btn-action confirm-btn"
                                                                data-confirm="This erases the record that they ever had access. Revoke instead if you want to keep the history.">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endpermission
                                                </td>
                                            </tr>

                                            @permission('farm-management.update')
                                                <tr class="collapse" id="editMember{{ $member->id }}">
                                                    <td colspan="7" class="bg-light">
                                                        <form action="{{ route('farm-management.members.update', $member->id) }}" method="POST">
                                                            @csrf @method('PUT')
                                                            <div class="row">
                                                                <div class="col-md-4 form-group mb-2">
                                                                    <label class="small mb-1">Role</label>
                                                                    <select name="role" class="form-control form-control-sm">
                                                                        <option value="manager" @selected($member->role === 'manager')>Manager</option>
                                                                        <option value="partner" @selected($member->role === 'partner')>Partner</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6 form-group mb-2">
                                                                    <label class="small mb-1 d-block">What they may do</label>
                                                                    {{-- Every column the form saves has to be listed
                                                                         here. tank_status_access and total_feed_access
                                                                         were missing, so they rendered unchecked
                                                                         whatever the member actually held — and the
                                                                         hidden value="0" beside each box then stripped
                                                                         both on save. --}}
                                                                    @include('admin.farm-management.partials._permissions', ['values' => [
                                                                        'view_access'        => $member->view_access,
                                                                        'edit_access'        => $member->edit_access,
                                                                        'tank_status_access' => $member->tank_status_access,
                                                                        'total_feed_access'  => $member->total_feed_access,
                                                                        'create_access'      => $member->create_access,
                                                                        'delete_access'      => $member->delete_access,
                                                                    ]])
                                                                </div>
                                                            </div>
                                                            <button type="submit" class="btn btn-sm btn-primary mb-2">
                                                                <i class="fas fa-save mr-1"></i> Save Access
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endpermission
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- --------------------------------------------------- History --}}
                    {{-- Who changed what on this farm. Thirty days here against
                         the app's fifteen: this is where a disagreement gets
                         settled, and the farmer ringing in has usually spent a
                         week noticing the problem before they call. --}}
                    <div class="tab-pane fade" id="tab-activity">
                        <form method="GET" class="form-inline mb-3">
                            {{-- Every other query parameter on this page is
                                 dropped by this form, so the filter is carried
                                 by itself and the tab is reopened on submit. --}}
                            <label class="mr-2 mb-0">Show</label>
                            <select name="activity_category" class="form-control form-control-sm mr-2"
                                onchange="this.form.submit()">
                                <option value="">Everything</option>
                                @foreach (\App\Models\FarmActivity::categories() as $key => $label)
                                    <option value="{{ $key }}" @selected(request('activity_category') === $key)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="small text-muted">Last {{ $activityWindow }} days</span>
                            @if (request()->filled('activity_category'))
                                <a href="{{ route('farm-management.farms.show', $farm->id) }}"
                                    class="btn btn-sm btn-outline-secondary ml-2">Clear</a>
                            @endif
                        </form>

                        @if ($activity->isEmpty())
                            <p class="text-muted mb-0">
                                Nothing has been changed on this farm in the last
                                {{ $activityWindow }} days.
                            </p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th style="white-space: nowrap;">When</th>
                                            <th>Who</th>
                                            <th>Tank</th>
                                            <th>Category</th>
                                            <th>What changed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($activity as $entry)
                                            <tr>
                                                <td style="white-space: nowrap;">
                                                    {{ $entry->created_at->format('d M Y') }}
                                                    <div class="small text-muted">
                                                        {{ $entry->created_at->format('h:i A') }}
                                                    </div>
                                                </td>
                                                <td>
                                                    {{ $entry->actor_name ?: 'Unknown' }}
                                                    <div class="small text-muted">
                                                        {{ $entry->actor_mobile }}
                                                        @if ($entry->actor_role)
                                                            <span class="badge badge-light text-capitalize">
                                                                {{ $entry->actor_role }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>{{ $entry->tank_name ?: '—' }}</td>
                                                <td>
                                                    <span class="badge badge-{{ $entry->action_colour }}">
                                                        {{ $entry->category_label }}
                                                    </span>
                                                </td>
                                                <td>{{ $entry->description }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Reopen the History tab after filtering it, so submitting the filter
         does not dump the admin back on Details with no idea what happened. --}}
    @if (request()->filled('activity_category'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var tab = document.querySelector('a[href="#tab-activity"]');
                if (tab && window.jQuery) {
                    window.jQuery(tab).tab('show');
                }
            });
        </script>
    @endif

@endsection

@push('scripts')
    <script>
        // Narrow the people list in place. Every farmer is already on the page,
        // so this is a filter rather than a search — no request, and a picked
        // person stays picked even when the filter hides them.
        (function () {
            var filter = document.getElementById('memberFilter');
            var select = document.getElementById('memberSelect');
            var count  = document.getElementById('memberFilterCount');

            if (!filter || !select) {
                return;
            }

            var options = Array.prototype.slice.call(select.options);

            function apply() {
                var term = filter.value.trim().toLowerCase();
                var shown = 0;

                options.forEach(function (option) {
                    var match = !term || option.text.toLowerCase().indexOf(term) !== -1;

                    // Never hide someone already chosen, or they would look
                    // deselected while still being submitted.
                    option.hidden = !match && !option.selected;

                    if (!option.hidden) {
                        shown++;
                    }
                });

                count.textContent = term
                    ? shown + ' of ' + options.length + ' people shown'
                    : '';
            }

            filter.addEventListener('input', apply);
            select.addEventListener('change', apply);
        })();
    </script>

    {{-- Start Crop: the stocking date decides whether prior feed is asked for.

         Delegated from the document, because every inactive tank has its own
         copy of this panel inside a collapsed row — binding per panel would
         mean rebinding whenever one opens. --}}
    <script>
        (function () {
            // Today in LOCAL time. toISOString() converts to UTC first, which
            // rolls the date over for anyone east of GMT and makes "today"
            // read as tomorrow.
            function todayStr() {
                var d = new Date();
                return d.getFullYear() + '-'
                    + String(d.getMonth() + 1).padStart(2, '0') + '-'
                    + String(d.getDate()).padStart(2, '0');
            }

            // Whole days from the chosen date up to YESTERDAY — the span the
            // server spreads the figure over. Today is excluded: the figure is
            // feed already used, and today's meals have not happened yet.
            function daysBefore(value) {
                if (!value) return 0;
                var days = Math.round(
                    (new Date(todayStr() + 'T00:00:00') - new Date(value + 'T00:00:00')) / 86400000
                );
                return days > 0 ? days : 0;
            }

            function sync(input) {
                var row   = input.closest('.row');
                if (!row) return;

                var wrap  = row.querySelector('.crop-feed-wrap');
                var label = row.querySelector('.crop-feed-label');
                var field = row.querySelector('.crop-feed');
                var hint  = row.querySelector('.crop-hint');
                if (!wrap) return;

                var days = daysBefore(input.value);

                if (days > 0) {
                    if (label) {
                        label.textContent = 'Feed used past ' + days
                            + ' day' + (days === 1 ? '' : 's') + ' (kg)';
                    }
                    if (hint) {
                        hint.textContent = 'Spread across the ' + days
                            + ' day' + (days === 1 ? '' : 's') + ' that have passed. '
                            + 'It does not come off the farm\u2019s store.';
                    }
                    wrap.style.display = '';
                } else {
                    // Moved back to today: there is no past to account for, so
                    // the figure goes with it rather than being sent for a day
                    // it cannot apply to.
                    if (field) field.value = '';
                    wrap.style.display = 'none';
                    if (hint) {
                        hint.textContent = 'Pick the day the crop went in. Choose a '
                            + 'past day and you will also be asked what it has '
                            + 'already been fed.';
                    }
                }
            }

            document.addEventListener('change', function (e) {
                if (e.target && e.target.classList.contains('crop-date')) sync(e.target);
            });
            document.addEventListener('input', function (e) {
                if (e.target && e.target.classList.contains('crop-date')) sync(e.target);
            });

            // The panels start on today, so nothing is shown until a date is
            // actually changed — but run once in case a value was restored.
            document.querySelectorAll('.crop-date').forEach(sync);
        })();
    </script>

    @include('admin.farm-management.partials._table-scripts', ['entity' => 'records'])
@endpush

@push('styles')
    <style>
        /* Fixed layout makes the colgroup widths binding rather than advisory. */
        .farm-details { table-layout: fixed; }

        /* Rows differ in height — Owner and Status carry a badge, the rest are
           plain text — so without this the label sat on the top edge of the tall
           rows and the two columns read as unaligned. */
        .farm-details th,
        .farm-details td { vertical-align: middle; }

        /* A long farm name or owner should wrap inside its cell rather than
           force the table wider than the card. */
        .farm-details td { word-break: break-word; }
    </style>
@endpush
