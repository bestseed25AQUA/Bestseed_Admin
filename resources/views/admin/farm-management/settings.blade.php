@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title d-flex align-items-center">
            <i class="fas fa-sliders-h mr-2"></i> Farm Management Settings
        </h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('farm-management.farms.index') }}">Farms</a></li>
                <li class="breadcrumb-item active" aria-current="page">Settings</li>
            </ol>
        </nav>
    </div>

    @include('admin.partials.flash')

    <form action="{{ route('farm-management.settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title">Free plan</h4>
                <p class="text-muted small">
                    What a farmer gets before paying. A farm beyond this needs a package, and a
                    free farm whose period has ended is <strong>locked</strong> — still readable,
                    with every record intact, but closed to new data until a package covers it.
                </p>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="farm_free_count">Free farms <span class="text-danger">*</span></label>
                        <select class="form-control" id="farm_free_count" name="farm_free_count" required>
                            @foreach (range(0, 5) as $n)
                                <option value="{{ $n }}" {{ (int) old('farm_free_count', $freeCount) === $n ? 'selected' : '' }}>
                                    {{ $n === 0 ? 'None — a package is needed from the first farm' : $n . ' free ' . Str::plural('farm', $n) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 form-group">
                        <label for="farm_free_months">Free for <span class="text-danger">*</span></label>
                        <select class="form-control" id="farm_free_months" name="farm_free_months" required>
                            @foreach ($durations as $m)
                                <option value="{{ $m }}" {{ (int) old('farm_free_months', $freeMonths) === $m ? 'selected' : '' }}>
                                    {{ $m === 0 ? 'Never expires' : $m . ' ' . Str::plural('month', $m) }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            After this the farm is locked until a package covers it.
                        </small>
                    </div>

                    <div class="col-md-4 form-group">
                        <label class="d-none d-md-block" aria-hidden="true">&nbsp;</label>
                        <div class="alert alert-light border py-2 px-3 mb-0 small">
                            <strong>{{ $farmsOnFree }}</strong>
                            {{ $farmsOnFree === 1 ? 'farm has' : 'farms have' }} used a free slot.
                            Changing these settings does not touch them — they keep the terms they
                            were created under.
                            @if ($legacyFarms > 0)
                                {{-- Said plainly, because it is the one thing this form cannot
                                     do: these predate the paid plan and never lock. --}}
                                <span class="d-block mt-1 text-muted">
                                    <strong>{{ $legacyFarms }}</strong> of them predate the paid
                                    plan and never expire.
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title">Demo video</h4>
                <p class="text-muted small">
                    Shown on the Farm Management screen to a farmer who has <strong>no farms yet</strong>,
                    in place of an empty page. It disappears once they create their first farm.
                    Leave the link blank to show nothing.
                </p>

                <div class="row">
                    <div class="col-md-8 form-group">
                        <label for="farm_demo_video_url">Video link</label>
                        <input type="url" class="form-control" id="farm_demo_video_url"
                               name="farm_demo_video_url"
                               value="{{ old('farm_demo_video_url', $videoUrl) }}"
                               placeholder="https://www.youtube.com/watch?v=…">
                    </div>

                    <div class="col-md-4 form-group">
                        <label for="farm_demo_video_title">Title</label>
                        <input type="text" class="form-control" id="farm_demo_video_title"
                               name="farm_demo_video_title"
                               value="{{ old('farm_demo_video_title', $videoTitle) }}">
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i> Save settings
        </button>
    </form>
</div>
@endsection
