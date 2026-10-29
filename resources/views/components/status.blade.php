@props(['value'])
@php
    $v = strtoupper((string) $value);
    $map = [
        'APPROVED' => 'badge-green', 'PAID' => 'badge-green', 'ACTIVE' => 'badge-green',
        'POSTED' => 'badge-green', 'VERIFIED' => 'badge-green', 'MATURED' => 'badge-blue',
        'PENDING' => 'badge-amber', 'PARTIAL' => 'badge-amber', 'DRAFT' => 'badge-amber',
        'REJECTED' => 'badge-rose', 'OVERDUE' => 'badge-rose', 'CANCELLED' => 'badge-rose',
        'DEFAULTED' => 'badge-rose', 'CLOSED' => 'badge-gray', 'EXPIRED' => 'badge-gray',
    ];
@endphp
<span class="{{ $map[$v] ?? 'badge-gray' }}">{{ ucfirst(strtolower($v)) }}</span>
