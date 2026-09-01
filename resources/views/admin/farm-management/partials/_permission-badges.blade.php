{{-- Compact permission badges. $row carries the *_access flags.

     The `managers` table predates the tank-status and total-feed permissions
     and has no such columns, so those two are shown only when the row
     actually carries them — `?? null` rather than `?? 0`, which would paint a
     misleading grey "off" badge on a row where the idea does not apply. --}}
@php
    $flags = array_filter([
        'View'        => $row->view_access ?? 0,
        'Edit'        => $row->edit_access ?? 0,
        'Tank On/Off' => $row->tank_status_access ?? null,
        'Total Feed'  => $row->total_feed_access ?? null,
        'Create'      => $row->create_access ?? 0,
        'Delete'      => $row->delete_access ?? 0,
    ], fn ($v) => $v !== null);
@endphp
@foreach ($flags as $label => $on)
    <span class="badge bg-{{ $on ? 'success' : 'light' }} {{ $on ? '' : 'text-muted' }}">{{ $label }}</span>
@endforeach
