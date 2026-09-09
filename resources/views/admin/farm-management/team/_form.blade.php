{{-- Fields shared by the add and edit screens for managers/partners. --}}
<div class="row">
    <div class="col-md-6 form-group">
        <label for="farm_id">Farm <span class="text-danger">*</span></label>
        <select class="form-control" id="farm_id" name="farm_id" required>
            <option value="">-- choose a farm --</option>
            @foreach ($farms as $farm)
                <option value="{{ $farm->id }}"
                    {{ (string) old('farm_id', $member->farm_id ?? ($selectedFarm ?? '')) === (string) $farm->id ? 'selected' : '' }}>
                    {{ $farm->farm_name }}
                    @if ($farm->farmer)
                        — owner: {{ trim($farm->farmer->first_name . ' ' . $farm->farmer->last_name) }}
                    @endif
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 form-group">
        <label for="is_partner">Role <span class="text-danger">*</span></label>
        @php
            // Existing row wins; otherwise fall back to the ?role= query the
            // "Add" button on a farm page passes through.
            $currentRole = $member->is_partner ?? (($selectedRole ?? 'manager') === 'partner' ? 1 : 0);
            $currentRole = (string) old('is_partner', $currentRole);
        @endphp
        <select class="form-control" id="is_partner" name="is_partner" required>
            <option value="0" {{ $currentRole === '0' ? 'selected' : '' }}>Manager</option>
            <option value="1" {{ $currentRole === '1' ? 'selected' : '' }}>Partner</option>
        </select>
    </div>

    {{-- People, found by mobile number — the app's "Give access to people"
         panel. Enter a number, add them, repeat: several managers or partners
         can go on in one go, and removing one leaves the rest alone.

         No Name box: a registered farmer already has one, and an unregistered
         number becomes a user from the number alone. --}}
    <div class="col-md-8 form-group">
        <label for="phone">People <span class="text-danger">*</span></label>
        <p class="text-muted small mb-2">
            Enter a 10-digit mobile number and add them. The same number can be on
            several farms, but only once per farm.
        </p>

        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-phone"></i></span>
            </div>
            <input type="text" class="form-control" id="phone"
                inputmode="numeric" maxlength="10" autocomplete="off"
                placeholder="e.g. 9704756582">
        </div>

        {{-- The match for what is typed: the registered person with an add
             button, or an amber panel offering to create that number. --}}
        <div id="phone_lookup" class="mt-2"></div>

        {{-- Everyone chosen so far. --}}
        <div id="chosen_people" class="mt-2"></div>

        {{-- What actually posts: [{phone, name}, ...]. --}}
        <input type="hidden" name="people" id="people"
               value="{{ old('people', '') }}">

        {{-- Edit screens act on one existing member, so the number stays a
             plain field there. --}}
        @isset($member)
            <input type="hidden" name="phone" value="{{ $member->phone }}">
            <input type="hidden" name="name" value="{{ $member->name }}">
        @endisset
    </div>
</div>

<hr>
<label class="d-block"><strong>What they may do on this farm</strong></label>
{{-- No $values when creating: the partial applies the same defaults the
     app's Setup Access screen uses. --}}
@include('admin.farm-management.partials._permissions', [
    'values' => $member ?? null,
    // $currentRole is the is_partner select's value, resolved above.
    'isPartner' => (string) $currentRole === '1',
])

@push('scripts')
    <script>
        // Pick several people by mobile number, the way the app's Setup Access
        // screen does: type a number, add them, repeat. Removing one leaves the
        // rest alone.
        //
        // A number is the only key — no partial-name search — so an admin
        // cannot pick the wrong "Ramesh" and hand a stranger the farm.
        $(function () {
            var $phone  = $('#phone');
            var $match  = $('#phone_lookup');
            var $list   = $('#chosen_people');
            var $hidden = $('#people');

            if (!$phone.length) return;

            var url    = @json(route('farm-management.team.lookup'));
            var chosen = [];          // [{phone, name}]
            var last   = null;

            try { chosen = JSON.parse($hidden.val() || '[]') || []; } catch (e) { chosen = []; }

            function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
            function initial(n) { return ((n || '?').trim()[0] || '?').toUpperCase(); }

            function avatar(name) {
                return '<div class="rounded-circle d-flex align-items-center justify-content-center mr-3" ' +
                       'style="width:40px;height:40px;background:#e8f0fe;color:#1a73e8;font-weight:600;flex:0 0 40px;">' +
                       esc(initial(name)) + '</div>';
            }

            function sync() {
                $hidden.val(JSON.stringify(chosen));

                if (!chosen.length) { $list.empty(); return; }

                var html = '<div class="border rounded"><div class="px-2 pt-2 small text-muted">' +
                           'Adding ' + chosen.length + ' ' +
                           (chosen.length === 1 ? 'person' : 'people') + '</div>';

                chosen.forEach(function (p, i) {
                    html += '<div class="d-flex align-items-center px-2 py-2 ' +
                            (i ? 'border-top' : '') + '">' +
                              avatar(p.name) +
                              '<div><div style="font-weight:600;">' + esc(p.name || p.phone) + '</div>' +
                              '<div class="text-muted small">' + esc(p.phone) + '</div></div>' +
                              '<button type="button" class="btn btn-sm btn-link text-danger ml-auto remove-person" ' +
                                      'data-phone="' + esc(p.phone) + '" title="Remove">' +
                                '<i class="fas fa-times"></i>' +
                              '</button>' +
                            '</div>';
                });

                $list.html(html + '</div>');
            }

            function has(phone) {
                return chosen.some(function (p) { return p.phone === phone; });
            }

            function add(phone, name) {
                if (has(phone)) return;
                chosen.push({ phone: phone, name: name || phone });
                sync();

                // Cleared so the next number can be typed straight away —
                // this is the part that makes adding several people work.
                $phone.val('');
                $match.empty();
                last = null;
                $phone.focus();
            }

            $list.on('click', '.remove-person', function () {
                var phone = String($(this).data('phone'));
                chosen = chosen.filter(function (p) { return p.phone !== phone; });
                sync();
            });

            function foundCard(res) {
                var name = res.name || ('Farmer #' + res.id);

                return '<div class="d-flex align-items-center border rounded p-2">' +
                         avatar(name) +
                         '<div><div style="font-weight:600;">' + esc(name) + '</div>' +
                         '<div class="text-muted small">' + esc(res.mobile) + '</div></div>' +
                         '<button type="button" class="btn btn-sm btn-outline-primary ml-auto add-person" ' +
                                 'data-phone="' + esc(res.mobile) + '" data-name="' + esc(name) + '">' +
                           '<i class="fas fa-plus mr-1"></i>Add' +
                         '</button>' +
                       '</div>';
            }

            function notFoundCard(mobile) {
                return '<div class="border rounded p-3" style="background:#fff8e6;border-color:#f0c36d !important;">' +
                         '<div style="color:#b26a00;font-weight:600;">' +
                           '<i class="fas fa-user-plus mr-2"></i>No one is using ' + esc(mobile) + ' yet' +
                         '</div>' +
                         '<div class="small mt-1 mb-2" style="color:#b26a00;">' +
                           'You can still add them. They will find this farm waiting the ' +
                           'first time they log in with this number.' +
                         '</div>' +
                         '<button type="button" class="btn btn-sm btn-outline-warning add-person" ' +
                                 'data-phone="' + esc(mobile) + '" data-name="">' +
                           '<i class="fas fa-user-plus mr-1"></i>Add ' + esc(mobile) +
                         '</button>' +
                       '</div>';
            }

            $match.on('click', '.add-person', function () {
                add(String($(this).data('phone')), String($(this).data('name') || ''));
            });

            function lookup() {
                var digits = ($phone.val() || '').replace(/\D/g, '');

                if (digits.length !== 10) { $match.empty(); last = null; return; }
                if (digits === last) return;
                last = digits;

                if (has(digits)) {
                    $match.html('<span class="text-muted small">Already in the list below.</span>');
                    return;
                }

                $match.html('<span class="text-muted small">Checking…</span>');

                $.getJSON(url, { mobile: digits })
                    .done(function (res) {
                        $match.html(res.found ? foundCard(res) : notFoundCard(digits));
                    })
                    .fail(function () {
                        // Silence would read as "no such user", a different thing.
                        $match.html('<span class="text-muted small">Could not check this number.</span>');
                        last = null;
                    });
            }

            $phone.on('input blur', lookup);

            // Enter adds the match instead of submitting a half-filled form.
            $phone.on('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); $match.find('.add-person').click(); }
            });

            sync();
        });
    </script>
@endpush
