{{--
    The four ability checkboxes shared by the "add team member" form, the edit
    form, and the give-access form.

    $values — array|object carrying view_access/edit_access/create_access/delete_access
--}}
@php
    // Whether the person being set up is a PARTNER. Decides one default below.
    // Null when the caller did not say, which is treated as a manager.
    $isPartner = (bool) ($isPartner ?? false);

    // Order matches the app's Setup Access screen.
    $abilities = [
        'view_access'        => ['View', 'See the farm, its tanks and feed history'],
        'edit_access'        => ['Edit', 'Change farm details, tanks and feed entries'],
        'tank_status_access' => ['Tank Active / Inactive', 'Mark a tank active or inactive (harvest it)'],
        'total_feed_access'  => ['Total Feed', 'Change the feed store and the low-feed alert limit'],
        'create_access'      => ['Create', 'Add tanks and record daily feed'],
        'delete_access'      => ['Delete', 'Remove the farm and its tanks'],
    ];
    // Defaults for a NEW member, matching the app's Setup Access screen:
    //   View  — on: there is no point admitting someone who cannot look.
    //   Edit  — on: a manager is brought in to run the farm day to day, and
    //           correcting what a tank was fed is the core of that.
    //   Tank Active/Inactive — depends on the role. Marking a tank inactive
    //           HARVESTS it, closing that tank's crop cycle: a partner's call
    //           to make, so they get it with the role, while a manager is
    //           handed it deliberately and starts without it.
    //   Total Feed — off: the store figure drives the low-feed alerts and every
    //           remaining-stock number, so it is handed over deliberately too.
    //   Create, Delete — off, for the same reason.
    $newMemberDefaults = [
        'view_access'        => 1,
        'edit_access'        => 1,
        'tank_status_access' => $isPartner ? 1 : 0,
        'total_feed_access'  => 0,
        'create_access'      => 0,
        'delete_access'      => 0,
    ];

    // toArray() for a model, NOT (array).
    //
    // Casting an Eloquent model with (array) yields its internal properties —
    // "\0*\0attributes", "\0*\0table" and so on — not its columns, so
    // $current['view_access'] was never found and the EDIT form rendered every
    // box unchecked however the member's permissions actually stood. Saving
    // that form then stripped them.
    if ($values instanceof \Illuminate\Contracts\Support\Arrayable) {
        $current = $values->toArray();
        $isNewMember = false;
    } elseif (is_array($values)) {
        $current = $values;
        $isNewMember = false;
    } else {
        // Nothing passed: this is a new member.
        $current = $newMemberDefaults;
        $isNewMember = true;
    }

    // The farm detail page includes this once per member row alongside the
    // grant form, so a fixed id would repeat. Duplicate ids make every
    // <label for> point at the first checkbox on the page — clicking a label
    // low down the table would silently tick a box in the form at the top.
    $uid = $idPrefix ?? uniqid('perm');
@endphp

<div class="row">
    @foreach ($abilities as $field => [$label, $hint])
        <div class="col-md-6 mb-2">
            {{-- pl-4 on the form-check: Bootstrap pulls .form-check-input out
                 with margin-left:-1.25rem, which put the box 20px LEFT of the
                 "What they may do on this farm" heading above it — outside the
                 card's padding entirely. The extra padding puts it back inside,
                 lined up with the rest of the form. --}}
            <div class="form-check pl-4">
                <input type="hidden" name="{{ $field }}" value="0">
                <input class="form-check-input" type="checkbox" id="{{ $uid }}_{{ $field }}"
                    name="{{ $field }}" value="1"
                    data-perm="{{ $field }}"
                    @if ($isNewMember) data-role-default @endif
                    {{ old($field, $current[$field] ?? 0) ? 'checked' : '' }}>
                <label class="form-check-label" for="{{ $uid }}_{{ $field }}">
                    <strong>{{ $label }}</strong>
                    <small class="d-block text-muted">{{ $hint }}</small>
                </label>
            </div>
        </div>
    @endforeach
</div>


{{-- Keep the Tank Active/Inactive default in step with the role picker.

     The role is chosen on the same form as these boxes, so a server-side
     default alone would be stale the moment someone switched Manager to
     Partner — they would have to know to tick the box themselves. This only
     ever touches a NEW member's box, and stops as soon as the box is ticked by
     hand, so a deliberate choice is never overwritten.

     @once because the partial is included once per member row on the farm
     page; without it the same listener would be attached a dozen times. --}}
@once
    @push('scripts')
        <script>
            (function () {
                function isPartner(select) {
                    var v = String(select.value).toLowerCase();
                    return v === 'partner' || v === '1';
                }

                document.addEventListener('change', function (e) {
                    var el = e.target;
                    if (!el || el.tagName !== 'SELECT') return;
                    if (el.name !== 'role' && el.name !== 'is_partner') return;

                    var form = el.closest('form');
                    if (!form) return;

                    var box = form.querySelector('input[data-perm="tank_status_access"][data-role-default]');
                    if (!box || box.dataset.touched) return;

                    box.checked = isPartner(el);
                });

                // Any hand-tick pins the box: the role picker leaves it alone
                // from then on.
                document.addEventListener('change', function (e) {
                    var el = e.target;
                    if (el && el.matches && el.matches('input[data-perm="tank_status_access"]')) {
                        el.dataset.touched = '1';
                    }
                });
            })();
        </script>
    @endpush
@endonce
