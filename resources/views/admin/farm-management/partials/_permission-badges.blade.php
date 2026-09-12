{{-- Compact permission badges. $row carries the *_access flags.

     `?? null` on tank-status and total-feed, not `?? 0`: those two columns were
     added to `managers` later, and this partial is also handed rows from other
     sources. Where the flag genuinely does not exist the badge is omitted,
     rather than painting a misleading grey "off" on an ability that was never
     asked about. --}}
@php
    $flags = array_filter([
        'View'        => $row->view_access ?? 0,
        'Edit'        => $row->edit_access ?? 0,
        'Tank On/Off' => $row->tank_status_access ?? null,
        'Store Stock' => $row->total_feed_access ?? null,
        'Create'      => $row->create_access ?? 0,
        'Delete'      => $row->delete_access ?? 0,
    ], fn ($v) => $v !== null);
@endphp
{{-- Inline styles rather than .badge utilities.
     The theme renders .badge as a large square block, so six of them per row
     came out as a cramped grid of rectangles with no gaps between them. These
     are pills: small, spaced, and readable at a glance down the column.

     Inline rather than a pushed <style> because this partial renders once per
     table row — a @push here would repeat the same CSS block for every member
     on the page. --}}
@php
    $base = 'display:inline-block;padding:2px 9px;margin:0 4px 4px 0;'
          . 'border-radius:11px;font-size:11px;font-weight:600;line-height:1.5;'
          . 'white-space:nowrap;border:1px solid;';

    // Granted reads at a glance; withheld stays legible but recedes, so a row
    // can be scanned for what someone CAN do.
    $onStyle  = $base . 'background:#e6f4ea;color:#137333;border-color:#b7e1c4;';
    $offStyle = $base . 'background:#f8f9fa;color:#9aa0a6;border-color:#e4e6e8;';
@endphp

<div style="display:flex;flex-wrap:wrap;">
    @foreach ($flags as $label => $on)
        <span style="{{ $on ? $onStyle : $offStyle }}">{{ $label }}</span>
    @endforeach
</div>
