{{-- Fields shared by the farm create and edit screens. --}}
<div class="row">
    <div class="col-md-6 form-group">
        <label for="farm_name">Farm Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="farm_name" name="farm_name"
            placeholder="e.g. Sattamma Thalli Farm - A Section"
            value="{{ old('farm_name', $farm->farm_name ?? '') }}" required>
    </div>

    <div class="col-md-6 form-group">
        <label for="farmer_id">Owner (Farmer) <span class="text-danger">*</span></label>
        <select class="form-control farmer-select2" id="farmer_id" name="farmer_id" required>
            <option value="">-- choose a farmer --</option>
            @foreach ($farmers as $farmer)
                <option value="{{ $farmer->id }}"
                    {{ (string) old('farmer_id', $farm->farmer_id ?? '') === (string) $farmer->id ? 'selected' : '' }}>
                    {{ trim($farmer->first_name . ' ' . $farmer->last_name) ?: 'Farmer #' . $farmer->id }}
                    — {{ $farmer->mobile }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">The owner sees this farm in the app and can give others access to it.</small>

        {{-- The owner's standing, fetched the moment one is picked.

             Admin could add a farmer their fifth farm without ever being told
             they were past the free limit — while the same farmer's app refused
             them at three. The two now read from one SubscriptionService, so
             admin sees what the farmer sees. --}}
        @if (!$farm)
            <div id="allowance_note" class="mt-2" style="display:none;"></div>
        @endif
    </div>

    <div class="col-md-4 form-group">
        <label for="status">Status <span class="text-danger">*</span></label>
        @php $currentStatus = (string) old('status', $farm->status ?? 1); @endphp
        <select class="form-control" id="status" name="status" required>
            <option value="1" {{ $currentStatus === '1' ? 'selected' : '' }}>Active</option>
            <option value="0" {{ $currentStatus === '0' ? 'selected' : '' }}>Inactive</option>
        </select>
        <small class="text-muted">Inactive farms stay intact but disappear from the app.</small>
    </div>

    <div class="col-md-4 form-group">
        <label for="no_of_tanks">Number of Tanks</label>
        <input type="number" min="0" class="form-control" id="no_of_tanks" name="no_of_tanks"
            placeholder="e.g. 6"
            value="{{ old('no_of_tanks', $farm->no_of_tanks ?? '') }}">
        <small class="text-muted">Set this first — a stocking date row appears for each tank below.</small>
    </div>

    {{-- The farm's own stocking date is NOT asked for.

         It is always the earliest of its tanks, and the controller derives it
         from them on every save — see the `$earliest` lookups in store() and
         update(). Offering a field for it invited the two to disagree, and the
         answer typed here lost to the tanks a moment later anyway. Each tank
         carries its own date in the rows below, which is the figure that
         actually drives day counts, reports and back-history. --}}

    {{-- Per-tank stocking dates, mirroring the app.
         Tanks are stocked as ponds are prepared, not all on one day, so each
         carries its own date. A tank stocked in the PAST also has feed history
         nothing here recorded, so that tank — and only that tank — is asked for
         the total already used. Rows are built by the script at the bottom and
         posted as the `tanks_meta` JSON the API already understands. --}}
    {{-- EDIT: the farm's current tanks, each with its own stocking date and
         prior-feed figure — the same per-tank model the app's edit screen uses.
         Changing either rewrites that tank's GENERATED history; feed recorded
         by hand is kept, because only generated rows carry the backfill mark. --}}
    @if ($farm && isset($tanks) && $tanks->count())
        <div class="col-12 form-group">
            <label class="d-block">Tank Stocking Dates</label>
            <small class="text-muted d-block mb-2">
                A tank dated before today is asked for the feed already used, spread
                across every day from stocking up to yesterday. That figure does NOT
                come off the store — it is history being recorded, not stock going out
                today. Changing a figure rebuilds only the generated history for that
                tank.
            </small>
            <div class="border rounded px-3 pt-3 pb-1" id="existing_tank_rows">
                @foreach ($tanks as $t)
                    @php
                        $tDate  = $t->stocking_date
                            ? \Illuminate\Support\Carbon::parse($t->stocking_date)->format('Y-m-d')
                            : '';
                        $tPrior = isset($backfill) ? $backfill->backfilledTotalFor((int) $t->id) : 0;
                    @endphp
                    <div class="row existing-tank-row" data-id="{{ $t->id }}">
                        <div class="col-md-6 form-group mb-2">
                            <label class="mb-1 small text-muted">
                                <strong>{{ $t->tank_name }}</strong> stocking date
                            </label>
                            <input type="date" class="form-control ex-tank-date"
                                   value="{{ $tDate }}" max="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-md-6 form-group mb-2 ex-feed-wrap">
                            <label class="mb-1 small text-muted ex-feed-label">Feed already used (kg)</label>
                            <input type="number" step="0.01" min="0" class="form-control ex-tank-feed"
                                   value="{{ $tPrior > 0 ? round($tPrior, 2) : '' }}" placeholder="Optional">
                        </div>
                    </div>
                @endforeach

                {{-- Tanks being ADDED continue this same list.
                     They were a second boxed section with its own heading
                     underneath, which read as a different kind of thing —
                     Tank5 belongs in the run of tanks, not in an annexe. --}}
                <div id="tank_rows" data-existing="{{ $tanks->count() }}"></div>
            </div>
        </div>

        <input type="hidden" name="existing_tanks_meta" id="existing_tanks_meta">
    @endif

    {{-- CREATE only: the farm has no tanks yet, so this box IS the list.
         On edit the same rows are appended inside the existing-tanks box
         above, so a newly added tank reads as the next tank rather than a
         separate section. --}}
    @if (!$farm)
        <div class="col-12 form-group" id="tank_rows_wrap" style="display:none;">
            <label class="d-block">Tank Stocking Dates</label>
            <small class="text-muted d-block mb-2">
                A tank dated before today is asked for the feed already used, which is
                spread across every day from stocking up to yesterday. That figure does
                NOT come off the store — it is history being recorded, not stock going
                out today.
            </small>
            <div id="tank_rows" class="border rounded px-3 pt-3 pb-1"
                 data-existing="0"></div>
        </div>
    @endif

    {{-- One name or the other: `tanks_meta` describes a farm's whole set at
         creation, `new_tanks_meta` describes only what is being appended. --}}
    <input type="hidden"
           name="{{ $farm ? 'new_tanks_meta' : 'tanks_meta' }}"
           id="tanks_meta"
           value="{{ old($farm ? 'new_tanks_meta' : 'tanks_meta') }}">

    {{-- Stock ON HAND — the same figure the app shows as Remaining Stock, not
         the raw `store` column. That column is the total ever put in and never
         moves as feed is recorded, so an admin looking at a farm down to 9,900
         of its original 10,000 saw 10,000 and would "correct" a number that was
         not wrong. The controller adds the recorded feed back on save. --}}
    <div class="col-md-4 form-group">
        <label for="store">Store (feed in stock now)</label>
        <input type="number" step="0.01" min="0" class="form-control" id="store" name="store"
            placeholder="Feed in the shed today, e.g. 3000"
            value="{{ old('store', isset($farm->id) ? app(App\Services\FarmStoreService::class)->remainingFor($farm) : '') }}">
        <small class="text-muted">What is in the shed today. Feed recorded from now on comes off it.</small>
    </div>

    <div class="col-md-4 form-group">
        <label for="low_feed_limit">Low Feed Alert Limit</label>
        <input type="number" step="0.01" min="0" class="form-control" id="low_feed_limit" name="low_feed_limit"
            placeholder="Warn below this, e.g. 500"
            value="{{ old('low_feed_limit', $farm->low_feed_limit ?? '') }}">
        <small class="text-muted">A notification fires when the store drops below this.</small>
    </div>

    {{-- Edit only. On create every tank supplies its own prior-feed figure in
         the rows above, so a second farm-wide box would either duplicate them
         or silently contradict them. --}}
    @if ($farm)
        {{-- Feed already used. Mirrors the app's field: a farm stocked in the past
             has history nobody recorded, and one figure fills it in. --}}
        <div class="col-md-4 form-group">
            <label for="feed_used_before">Total Feed Used (kg)</label>
            <input type="number" step="0.01" min="0" class="form-control"
                id="feed_used_before" name="feed_used_before"
                placeholder="Total fed so far, e.g. 1170"
                value="{{ old('feed_used_before', isset($farm->id)
                    ? round((float) ($farm->feed_used_before ?? 0)
                        + app(App\Services\FarmStoreService::class)->recordedFeedFor($farm), 2)
                    : '') }}">
            <small class="text-muted">
                @isset($farm)
                    Changing this rebuilds the generated history. Feed recorded by hand is kept.
                @else
                    For a farm stocked in the past. Spread evenly over every tank and every day
                    since the stocking date.
                @endisset
            </small>
        </div>
    @endif

    {{-- Farm photos. The app shows up to two, so uploading replaces the set
         rather than appending — otherwise a new photo could land third and
         never be seen. --}}
    <div class="col-md-12 form-group">
        <label for="images">Farm Photos</label>
        <input type="file" class="form-control-file" id="images" name="images[]"
            accept="image/*" multiple>
        <small class="text-muted">Up to 2 images, 5&nbsp;MB each. Uploading replaces the current photos.</small>

        @error('images.*')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror

        @php
            $existingImages = [];
            if (isset($farm) && $farm->images && $farm->images->images) {
                $decoded = json_decode($farm->images->images, true);
                $existingImages = is_array($decoded) ? $decoded : [];
            }
        @endphp

        @if (!empty($existingImages))
            <div class="d-flex flex-wrap mt-2">
                @foreach ($existingImages as $image)
                    <img src="{{ $image }}" alt="Farm photo"
                        class="mr-2 mb-2 rounded border"
                        style="width: 120px; height: 90px; object-fit: cover;"
                        onerror="this.style.display='none'">
                @endforeach
            </div>
        @endif
    </div>
</div>


{{-- Searchable owner picker.
     The farmer list runs to dozens of rows, and a native <select> renders one
     long unscrollable menu that ran off the bottom of the screen. Select2 is
     what the booking screens already use for the same problem, so the same
     library and CDN version are used here rather than introducing a second
     one. --}}
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* One height for every control on this form.
           The theme sizes a text .form-control by padding (0.875rem top and
           bottom, 1rem line, 2px border ≈ 46px) but pins native selects to
           calc(2.25rem + 2px) ≈ 38px, so dropdowns sat visibly shorter than the
           inputs beside them. Both are pinned to the text-input height here, and
           Select2's box is matched to it as well. Page-scoped: this block is
           pushed only by the farm form. */
        .form-control,
        select.form-control:not([size]):not([multiple]),
        .select2-container--default .select2-selection--single {
            height: calc(2.75rem + 2px);
        }
        /* Select2 draws its own inner text and arrow, which do not inherit the
           box's padding — centre them so the value sits on the same baseline as
           a typed value in the field next to it. */
        .select2-container--default .select2-selection--single {
            display: flex;
            align-items: center;
            padding: 0 1.375rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: normal !important;
            padding-left: 0;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 100%;
            display: flex;
            align-items: center;
        }
        /* The whole point: a bounded, scrollable menu instead of a list that
           grows past the bottom of the window. */
        .select2-container--default .select2-results > .select2-results__options {
            max-height: 260px;
            overflow-y: auto;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // The layout renders @stack('scripts') twice, so this can run twice.
        // hasClass('select2-hidden-accessible') is Select2's own marker for an
        // already-initialised element — checking it keeps the second pass a
        // no-op instead of rebuilding the widget under the user.
        $(function () {
            var $owner = $('.farmer-select2');

            if (!$.fn.select2 || !$owner.length) return;
            if ($owner.hasClass('select2-hidden-accessible')) return;

            $owner.select2({
                placeholder: '-- choose a farmer --',
                allowClear: true,
                width: '100%'
            });

            // Tell people what the box actually matches on.
            //
            // Select2 has no option for the search field's placeholder, and the
            // field is created fresh each time the menu opens, so it is set on
            // the open event rather than once at init. Scoped to
            // .select2-container--open so a second Select2 elsewhere on the page
            // cannot be relabelled by this.
            $owner.on('select2:open', function () {
                var field = document.querySelector(
                    '.select2-container--open .select2-search__field'
                );
                if (field) {
                    field.setAttribute('placeholder', 'Search by name or mobile number');
                }
            });
        });

        // ── Owner's farm allowance ───────────────────────────────────────
        //
        // Asked the moment an owner is picked, so admin knows before filling in
        // the rest of the form — not after submitting it. Create only: an
        // existing farm has already used its slot.
        @if (!$farm)
        $(function () {
            var $owner = $('#farmer_id');
            var $note  = $('#allowance_note');

            if (!$owner.length || !$note.length) return;

            var URL = "{{ url('admin/farm-management/farmers') }}";
            var token = 0;   // only the newest reply is allowed to render

            function box(cls, icon, html) {
                return '<div class="alert ' + cls + ' py-2 px-3 mb-0 small">'
                     + '<i class="fas ' + icon + ' mr-1"></i>' + html + '</div>';
            }

            // The tick that lets a refused farm through. Added and removed with
            // the warning, so it cannot be left checked from an earlier owner.
            function overrideHtml() {
                return '<div class="form-check mt-2 pl-4">'
                     + '<input class="form-check-input" type="checkbox" '
                     + 'id="override_allowance" name="override_allowance" value="1">'
                     + '<label class="form-check-label small" for="override_allowance">'
                     + '<strong>Create anyway</strong> — the farmer has paid another way, '
                     + 'or this farm is being set up on their behalf.'
                     + '</label></div>';
            }

            function render(d) {
                var owns = d.owned_farms + ' farm' + (d.owned_farms === 1 ? '' : 's')
                         + ' already, free limit ' + d.free_limit;

                if (d.can_create) {
                    var how = d.subscription
                        ? ' Subscribed until ' + d.subscription.expires_on + '.'
                        : '';
                    $note.html(box('alert-success', 'fa-check-circle',
                        '<strong>' + owns + '.</strong> Can create another.' + how));
                } else {
                    $note.html(box('alert-warning', 'fa-exclamation-triangle',
                        '<strong>' + owns + '.</strong> ' + (d.message || '')
                        + overrideHtml()));
                }

                $note.show();
            }

            function check() {
                var id = $owner.val();
                $note.hide().empty();

                if (!id) return;

                var mine = ++token;
                $note.html(box('alert-light', 'fa-spinner fa-spin', 'Checking this farmer…')).show();

                $.getJSON(URL + '/' + id + '/allowance')
                    .done(function (res) {
                        if (mine !== token) return;          // a newer pick won
                        if (res && res.status) render(res.data);
                        else $note.hide().empty();
                    })
                    .fail(function () {
                        if (mine !== token) return;
                        // Never block on a failed check — say so and let the
                        // save decide, which checks again server-side anyway.
                        $note.html(box('alert-light', 'fa-info-circle',
                            'Could not check this farmer\u2019s allowance.'));
                    });
            }

            // select2 fires its own event; `change` covers both it and a plain
            // select if the plugin ever fails to load.
            $owner.on('change select2:select', check);

            // An owner restored after a validation error should be checked too.
            if ($owner.val()) check();
        });
        @endif

        // ── Per-tank stocking dates ──────────────────────────────────────
        //
        // Same flow as the app: choose how many tanks, get a date row for
        // each, and a tank dated in the past also asks for the feed already
        // used. The rows are collected into `tanks_meta` on submit.
        $(function () {
            var $count   = $('#no_of_tanks');
            var $wrap    = $('#tank_rows_wrap');
            var $rows    = $('#tank_rows');
            var $meta    = $('#tanks_meta');

            if (!$count.length || !$rows.length) return;

            // Today as yyyy-mm-dd in LOCAL time. toISOString() would convert to
            // UTC first, which rolls the date over for anyone east of GMT and
            // made "today" read as tomorrow.
            function todayStr() {
                var d = new Date();
                var m = String(d.getMonth() + 1).padStart(2, '0');
                var day = String(d.getDate()).padStart(2, '0');
                return d.getFullYear() + '-' + m + '-' + day;
            }

            var TODAY = todayStr();

            // Two even columns, with the tank's name folded into the date
            // label. It used to sit in its own col-md-3, which left a quarter
            // of every row empty between the name and the field it belonged to.
            // Tanks the farm ALREADY has. Rows are numbered on from here, so on
            // edit the first new row is Tank5 rather than a second Tank1.
            var BASE = parseInt($rows.data('existing'), 10) || 0;

            function rowHtml(i) {
                return '' +
                  '<div class="row tank-row" data-index="' + i + '">' +
                    '<div class="col-md-6 form-group mb-2">' +
                      '<label class="mb-1 small text-muted">' +
                        '<strong>Tank' + (BASE + i + 1) + '</strong> stocking date' +
                      '</label>' +
                      '<input type="date" class="form-control tank-date" max="' + TODAY + '">' +
                    '</div>' +
                    '<div class="col-md-6 form-group mb-2 tank-feed-wrap" style="display:none;">' +
                      '<label class="mb-1 small text-muted tank-feed-label">Feed already used (kg)</label>' +
                      '<input type="number" step="0.01" min="0" class="form-control tank-feed" placeholder="Optional">' +
                    '</div>' +
                  '</div>';
            }

            // Whole days from a tank's date up to YESTERDAY — the span the
            // server spreads the figure over. Today is excluded: the figure is
            // feed already used, and today's meals have not happened yet.
            function daysBefore(dateStr) {
                if (!dateStr) return 0;
                var from = new Date(dateStr + 'T00:00:00');
                var to   = new Date(TODAY + 'T00:00:00');
                var days = Math.round((to - from) / 86400000);
                return days > 0 ? days : 0;
            }

            function syncRow($row) {
                var date = $row.find('.tank-date').val();
                var days = daysBefore(date);
                var $feed = $row.find('.tank-feed-wrap');

                if (days > 0) {
                    $row.find('.tank-feed-label')
                        .text('Feed used past ' + days + ' day' + (days === 1 ? '' : 's') + ' (kg)');
                    $feed.show();
                } else {
                    // Moved to today or later: there is no past to account for,
                    // so the figure goes with it rather than being sent for a
                    // date it cannot apply to.
                    $row.find('.tank-feed').val('');
                    $feed.hide();
                }
            }

            function build() {
                var total = parseInt($count.val(), 10);
                if (isNaN(total) || total < 1) { $wrap.hide(); $rows.empty(); return; }

                // Cap matches the app's tank picker, and keeps a mistyped 5000
                // from locking the browser building rows.
                total = Math.min(total, 50);

                // Only the tanks being ADDED. On create BASE is 0 and this is
                // the whole set; on edit it is whatever the count was raised
                // by. Lowering the count below what the farm already has adds
                // nothing — the server refuses to destroy tanks from here, so
                // offering rows for it would be a promise the save cannot keep.
                var n = total - BASE;
                if (n < 1) { $wrap.hide(); $rows.empty(); return; }

                var existing = $rows.find('.tank-row').length;

                // Grow and shrink rather than rebuild, so dates already typed
                // survive a change to the count.
                if (n > existing) {
                    for (var i = existing; i < n; i++) $rows.append(rowHtml(i));
                } else if (n < existing) {
                    $rows.find('.tank-row').slice(n).remove();
                }

                $wrap.show();
            }

            $count.on('input change', build);

            $rows.on('change input', '.tank-date', function () {
                syncRow($(this).closest('.tank-row'));
            });

            // Collect on submit.
            $count.closest('form').on('submit', function () {
                var meta = [];

                $rows.find('.tank-row').each(function () {
                    var $r = $(this);
                    meta.push({
                        stocking_date: $r.find('.tank-date').val() || null,
                        feed_used_before: $r.find('.tank-feed').val() || 0
                    });
                });

                // The farm's own stocking date is not sent: the controller
                // derives it from the earliest tank on every save.
                $meta.val(meta.length ? JSON.stringify(meta) : '');
            });

            build();
        });

        // ── Existing tanks (edit) ────────────────────────────────────────
        //
        // Same rules as the create rows: the feed box belongs to a tank dated
        // in the past, and its label names the span the figure is spread over.
        $(function () {
            var $rows = $('#existing_tank_rows');
            var $meta = $('#existing_tanks_meta');

            if (!$rows.length) return;

            function todayStr() {
                var d = new Date();
                return d.getFullYear() + '-' +
                       String(d.getMonth() + 1).padStart(2, '0') + '-' +
                       String(d.getDate()).padStart(2, '0');
            }

            var TODAY = todayStr();

            function daysBefore(dateStr) {
                if (!dateStr) return 0;
                var days = Math.round(
                    (new Date(TODAY + 'T00:00:00') - new Date(dateStr + 'T00:00:00')) / 86400000
                );
                return days > 0 ? days : 0;
            }

            function syncRow($row) {
                var days = daysBefore($row.find('.ex-tank-date').val());
                var $wrap = $row.find('.ex-feed-wrap');

                if (days > 0) {
                    $row.find('.ex-feed-label')
                        .text('Feed used past ' + days + ' day' + (days === 1 ? '' : 's') + ' (kg)');
                    $wrap.show();
                } else {
                    // Moved to today or later: nothing left to account for.
                    $row.find('.ex-tank-feed').val('');
                    $wrap.hide();
                }
            }

            $rows.find('.existing-tank-row').each(function () { syncRow($(this)); });

            $rows.on('change input', '.ex-tank-date', function () {
                syncRow($(this).closest('.existing-tank-row'));
            });

            $rows.closest('form').on('submit', function () {
                var meta = [];

                $rows.find('.existing-tank-row').each(function () {
                    var $r = $(this);
                    meta.push({
                        id: $r.data('id'),
                        stocking_date: $r.find('.ex-tank-date').val() || null,
                        feed_used_before: $r.find('.ex-tank-feed').val() || 0
                    });
                });

                $meta.val(meta.length ? JSON.stringify(meta) : '');
            });
        });
    </script>
@endpush
