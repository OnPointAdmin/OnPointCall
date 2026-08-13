@props([
    'size' => 'sm',
    'fill' => false,
])

@php
    $imgHeight = match (true) {
        $fill => '100%',
        $size === 'lg' => '3.75rem',
        $size === 'md' => '2.75rem',
        default => '2.25rem',
    };
    $fontSize = match ($size) {
        'lg' => '2.25rem',
        'md' => '1.375rem',
        default => '1.125rem',
    };
    $color = match ($size) {
        'md' => '#0f172a',
        default => '#1d4ed8',
    };
    $rowStyle = $fill
        ? 'display:flex;align-items:center;gap:0.75rem;height:100%'
        : 'display:flex;align-items:center;gap:0.5rem';
@endphp

<div {{ $attributes->merge(['style' => $rowStyle]) }}>
    <img
        src="{{ asset('images/onpoint-call.webp') }}"
        alt="{{ config('app.name') }}"
        style="height: {{ $imgHeight }}; width: auto; flex-shrink: 0;"
    >
    <span style="font-size: {{ $fontSize }}; font-weight: 600; color: {{ $color }}; white-space: nowrap; line-height: 1.2;">
        {{ config('app.name') }}
    </span>
</div>
