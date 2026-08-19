{{--
    The four ability checkboxes shared by the "add team member" form, the edit
    form, and the access-code generator.

    $values — array|object carrying view_access/edit_access/create_access/delete_access
--}}
@php
    $abilities = [
        'view_access'   => ['View',   'See the farm, its tanks and feed history'],
        'create_access' => ['Create', 'Add tanks and record daily feed'],
        'edit_access'   => ['Edit',   'Change farm details, tanks and feed entries'],
        'delete_access' => ['Delete', 'Remove the farm and its tanks'],
    ];
    $current = (array) ($values ?? []);
@endphp

<div class="row">
    @foreach ($abilities as $field => [$label, $hint])
        <div class="col-md-6 mb-2">
            <div class="form-check">
                <input type="hidden" name="{{ $field }}" value="0">
                <input class="form-check-input" type="checkbox" id="{{ $field }}"
                    name="{{ $field }}" value="1"
                    {{ old($field, $current[$field] ?? 0) ? 'checked' : '' }}>
                <label class="form-check-label" for="{{ $field }}">
                    <strong>{{ $label }}</strong>
                    <small class="d-block text-muted">{{ $hint }}</small>
                </label>
            </div>
        </div>
    @endforeach
</div>
