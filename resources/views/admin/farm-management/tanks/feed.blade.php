@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-utensils mr-2"></i>{{ $tank->tank_name }} — Feed Records
            </h3>
            @permission('farm-management.view')
                <a href="{{ route('farm-management.tanks.feed.report', [$farm->id, $tank->id]) }}"
                    class="btn btn-sm btn-outline-primary float-right">
                    <i class="fas fa-download mr-1"></i> Download CSV
                </a>
            @endpermission

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('farm-management.farms.index') }}">Farms</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('farm-management.farms.show', $farm->id) }}">{{ $farm->farm_name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $tank->tank_name }}</li>
                </ol>
            </nav>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-0">{{ number_format((float) $tank->total_feed_used, 2) }} kg</h5>
                        <small class="text-muted">Total feed used</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-0">{{ $entries->pluck('feed_date')->unique()->count() }}</h5>
                        <small class="text-muted">Days with a record</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-0">{{ $tank->stocking_date ?? '-' }}</h5>
                        <small class="text-muted">Stocking date</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Every crop cycle this tank has carried.

             The farmer's app shows only the CURRENT one — a finished batch
             leaves the farm totals, and an earlier one cannot be reached from
             the app at all — so this is the only place the whole history of a
             re-used tank is visible. --}}
        @if ($batches->isNotEmpty())
            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="card-title">Batches ({{ $batches->count() }})</h4>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Batch</th><th>Stocked</th><th>Finished</th>
                                    <th>Days fed</th><th>Feed used</th><th>Status</th>
                                    <th class="text-center">Records</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($batches as $batch)
                                    <tr @class(['table-active' => $selected && $selected->id === $batch->id])>
                                        <td class="font-weight-bold">#{{ $batch->batch_no }}</td>
                                        <td>{{ optional($batch->stocking_date)->format('d M Y') ?? '-' }}</td>
                                        <td>{{ optional($batch->ended_at)->format('d M Y') ?? '-' }}</td>
                                        <td>{{ $batch->fed_days }}</td>
                                        <td>{{ $batch->feed_total }}</td>
                                        <td>
                                            @if ($batch->ended_at)
                                                <span class="badge bg-secondary">Finished</span>
                                            @else
                                                <span class="badge bg-success">Running</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a class="btn btn-sm btn-info btn-action"
                                               href="{{ route('farm-management.tanks.feed', [$farm->id, $tank->id]) }}?batch={{ $batch->id }}"
                                               title="Show this batch's records">
                                                <i class="fas fa-list"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($selected)
                        <small class="text-muted d-block mt-2">
                            Showing the records of batch #{{ $selected->batch_no }}.
                        </small>
                    @endif
                </div>
            </div>
        @endif

        @permission('farm-management.create')
            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="card-title">Add a feed entry</h4>

                    <form action="{{ route('farm-management.tanks.feed.store', [$farm->id, $tank->id]) }}" method="POST">
                        @csrf
                        <div class="row align-items-end">
                            <div class="col-md-3 form-group">
                                <label>Date</label>
                                <input type="date" name="feed_date" class="form-control"
                                    value="{{ old('feed_date', now()->toDateString()) }}" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Meals</label>
                                <input type="number" step="0.01" min="0" name="meals" class="form-control"
                                    value="{{ old('meals') }}" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Feed Quantity (kg)</label>
                                <input type="number" step="0.01" min="0" name="feed_quantity" class="form-control"
                                    value="{{ old('feed_quantity') }}" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-plus mr-1"></i> Add Entry
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endpermission

        <div class="card">
            <div class="card-body">
                @if ($entries->isEmpty())
                    <p class="text-muted mb-0">No feed has been recorded for this tank yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th><th>Date</th><th>Meals</th><th>Quantity (kg)</th>
                                    <th>Source</th><th>Recorded</th><th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entries as $entry)
                                    <tr>
                                        <td>{{ $entry->id }}</td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($entry->feed_date)->format('d M Y') }}</td>
                                        <td>{{ $entry->meals }}</td>
                                        <td>{{ number_format((float) $entry->feed_quantity, 2) }}</td>
                                        <td>
                                            @if ($entry->is_backfill)
                                                {{-- Spread automatically across past days when the farm was
                                                     created with a "feed already used" figure. --}}
                                                <span class="badge bg-secondary">Backfilled</span>
                                            @else
                                                <span class="badge bg-info">Logged</span>
                                            @endif
                                        </td>
                                        <td>{{ optional($entry->created_at)->format('d M Y H:i') ?? '-' }}</td>
                                        <td class="text-center text-nowrap">
                                            @permission('farm-management.update')
                                                <button class="btn btn-sm btn-primary btn-action" title="Edit entry"
                                                    data-toggle="collapse" data-target="#editFeed{{ $entry->id }}">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            @endpermission
                                            @permission('farm-management.delete')
                                                <form action="{{ route('farm-management.tanks.feed.destroy', [$farm->id, $tank->id, $entry->id]) }}"
                                                    method="POST" class="d-inline confirm-form">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="btn btn-sm btn-danger btn-action confirm-btn"
                                                        data-confirm="The tank's total feed used is recalculated without this entry.">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endpermission
                                        </td>
                                    </tr>

                                    @permission('farm-management.update')
                                        <tr class="collapse" id="editFeed{{ $entry->id }}">
                                            <td colspan="7" class="bg-light">
                                                <form action="{{ route('farm-management.tanks.feed.update', [$farm->id, $tank->id, $entry->id]) }}"
                                                    method="POST">
                                                    @csrf @method('PUT')
                                                    <div class="row align-items-end">
                                                        <div class="col-md-3 form-group mb-2">
                                                            <label class="small mb-1">Meals</label>
                                                            <input type="number" step="0.01" min="0" name="meals"
                                                                class="form-control form-control-sm" value="{{ $entry->meals }}" required>
                                                        </div>
                                                        <div class="col-md-3 form-group mb-2">
                                                            <label class="small mb-1">Feed Quantity (kg)</label>
                                                            <input type="number" step="0.01" min="0" name="feed_quantity"
                                                                class="form-control form-control-sm" value="{{ $entry->feed_quantity }}" required>
                                                        </div>
                                                        <div class="col-md-3 form-group mb-2">
                                                            <button type="submit" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-save mr-1"></i> Save Entry
                                                            </button>
                                                        </div>
                                                        <div class="col-md-3 form-group mb-2">
                                                            <small class="text-muted">
                                                                The date cannot be changed — delete and re-add to move an
                                                                entry to another day.
                                                            </small>
                                                        </div>
                                                    </div>
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
        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.farm-management.partials._table-scripts', ['entity' => 'feed entries'])
@endpush
