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
    $current = (array) ($values ?? []);

    // The farm detail page includes this once per member row alongside the
    // grant form, so a fixed id would repeat. Duplicate ids make every
    // <label for> point at the first checkbox on the page — clicking a label
    // low down the table would silently tick a box in the form at the top.
    $uid = $idPrefix ?? uniqid('perm');
@endphp

<div class="row">
    @foreach ($abilities as $field => [$label, $hint])
        <div class="col-md-6 mb-2">
            <div class="form-check">
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
