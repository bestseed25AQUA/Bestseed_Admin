{{--
    The four ability checkboxes shared by the "add team member" form, the edit
    form, and the give-access form.

    $values — array|object carrying view_access/edit_access/create_access/delete_access
--}}
@php
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
    //   Tank Active/Inactive — off: marking a tank inactive HARVESTS it, closing
    //           that tank's crop cycle, so it is handed over deliberately.
    //   Total Feed — off: the store figure drives the low-feed alerts and every
    //           remaining-stock number, so it is handed over deliberately too.
    //   Create, Delete — off, for the same reason.
    $newMemberDefaults = [
        'view_access'        => 1,
        'edit_access'        => 1,
        'tank_status_access' => 0,
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
    } elseif (is_array($values)) {
        $current = $values;
    } else {
        // Nothing passed: this is a new member.
        $current = $newMemberDefaults;
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
                    {{ old($field, $current[$field] ?? 0) ? 'checked' : '' }}>
                <label class="form-check-label" for="{{ $uid }}_{{ $field }}">
                    <strong>{{ $label }}</strong>
                    <small class="d-block text-muted">{{ $hint }}</small>
                </label>
            </div>
        </div>
    @endforeach
</div>
