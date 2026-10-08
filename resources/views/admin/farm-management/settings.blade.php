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

    <form action="{{ route('farm-management.settings.update') }}" method="POST"
          enctype="multipart/form-data">
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
                    Leave both boxes blank to show nothing.
                </p>

                @php
                    // An uploaded file is stored as a path under uploads/farm/;
                    // anything else is an absolute link somebody pasted.
                    $isUploaded  = $videoUrl && !Str::startsWith($videoUrl, ['http://', 'https://']);
                    $previewSrc  = $isUploaded ? asset($videoUrl) : $videoUrl;
                @endphp

                <div class="row">
                    <div class="col-md-8 form-group">
                        <label for="farm_demo_video_file">
                            Upload a video <span class="text-muted">(recommended)</span>
                        </label>
                        <input type="file" class="form-control-file" id="farm_demo_video_file"
                               name="farm_demo_video_file"
                               accept="video/mp4,video/quicktime,video/webm,video/x-m4v">
                        <small class="form-text text-muted">
                            {{ strtoupper(implode(', ', \App\Http\Controllers\Admin\FarmSettingsController::VIDEO_MIMES)) }}
                            &middot; up to <strong>{{ $videoMaxMb }} MB</strong>.
                            A file plays inside the app; a link opens the browser.
                        </small>

                        <div class="alert alert-danger py-2 px-3 mt-2 d-none small"
                             id="videoTooLarge"></div>


                        @if ($isUploaded)
                            <div class="mt-3">
                                <video src="{{ $previewSrc }}" controls preload="metadata"
                                       style="max-width:100%; max-height:220px; border-radius:6px;
                                              background:#000;"></video>
                                <div class="mt-1">
                                    <small class="text-muted d-block mb-2">
                                        Currently uploaded. Choosing a new file replaces it.
                                    </small>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input"
                                               id="farm_demo_video_remove"
                                               name="farm_demo_video_remove" value="1">
                                        <label class="custom-control-label text-danger"
                                               for="farm_demo_video_remove">
                                            Remove this video and show nothing
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-md-4 form-group">
                        <label for="farm_demo_video_title">Title</label>
                        <input type="text" class="form-control" id="farm_demo_video_title"
                               name="farm_demo_video_title"
                               value="{{ old('farm_demo_video_title', $videoTitle) }}">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 form-group mb-0">
                        <label for="farm_demo_video_url">
                            …or paste a link
                        </label>
                        <input type="url" class="form-control" id="farm_demo_video_url"
                               name="farm_demo_video_url"
                               value="{{ old('farm_demo_video_url', $isUploaded ? '' : $videoUrl) }}"
                               placeholder="https://www.youtube.com/watch?v=…">
                        <small class="form-text text-muted">
                            Uploading a file replaces this, and pasting a link here deletes
                            any uploaded file. Leave both alone to keep the current video.
                        </small>

                        @if ($videoUrl && !$isUploaded)
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input"
                                       id="farm_demo_video_remove"
                                       name="farm_demo_video_remove" value="1">
                                <label class="custom-control-label text-danger"
                                       for="farm_demo_video_remove">
                                    Remove this video and show nothing
                                </label>
                            </div>
                        @endif
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

@push('scripts')
    <script>
        // Refuse an oversized video before it is uploaded.
        (function () {

            var input = document.getElementById('farm_demo_video_file');
            var alert = document.getElementById('videoTooLarge');

            if (!input || !alert) {
                return; // No permission to edit, or the field is not rendered.
            }

            var maxBytes = {{ (int) $videoMaxBytes }};
            var maxLabel = '{{ $videoMaxMb }} MB';

            input.addEventListener('change', function () {
                var file = input.files && input.files[0];

                alert.classList.add('d-none');
                input.setCustomValidity('');

                if (!file || file.size <= maxBytes) {
                    return;
                }

                var mb = (file.size / (1024 * 1024)).toFixed(1);

                alert.textContent =
                    'This video is ' + mb + ' MB. Please choose one under ' + maxLabel + '.';
                alert.classList.remove('d-none');

                // Blocks submit with the browser's own message, so the form
                // cannot be sent on a file that is certain to be refused.
                input.setCustomValidity('This video is larger than ' + maxLabel + '.');
                input.value = '';
            });
        })();
    </script>
@endpush
