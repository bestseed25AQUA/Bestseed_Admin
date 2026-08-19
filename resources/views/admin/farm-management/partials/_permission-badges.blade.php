{{-- Compact view/create/edit/delete badges. $row carries the four *_access flags. --}}
@php
    $flags = [
        'View'   => $row->view_access ?? 0,
        'Create' => $row->create_access ?? 0,
        'Edit'   => $row->edit_access ?? 0,
        'Delete' => $row->delete_access ?? 0,
    ];
@endphp
@foreach ($flags as $label => $on)
    <span class="badge bg-{{ $on ? 'success' : 'light' }} {{ $on ? '' : 'text-muted' }}">{{ $label }}</span>
@endforeach
