{{-- revoked / expired / active / pending, straight from the model's own logic. --}}
@php
    $status = $grant->statusLabel();
    $colour = ['active' => 'success', 'pending' => 'warning', 'expired' => 'secondary', 'revoked' => 'danger'][$status] ?? 'secondary';
@endphp
<span class="badge bg-{{ $colour }}">{{ ucfirst($status) }}</span>
