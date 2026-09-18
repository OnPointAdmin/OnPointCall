@props([
    'size' => 'sm',
    'fill' => false,
    'stack' => false,
])

@php
    $imgHeight = match (true) {
        $fill && ! $stack => '100%',
        $size === 'lg' => '3.75rem',
        $size === 'md' => '2.75rem',
        default => '2.25rem',
    };
    $fontSize = match ($size) {
        'lg' => $stack ? '1.75rem' : '2.25rem',
        'md' => '1.375rem',
        default => '1.125rem',
    };
    $color = match ($size) {
        'md' => '#0f172a',
        default => '#1d4ed8',
    };
    $rowStyle = $stack
        ? 'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.75rem;width:100%'
        : ($fill
            ? 'display:flex;align-items:center;gap:0.75rem;height:100%'
            : 'display:flex;align-items:center;gap:0.5rem');
    $nameStyle = $stack
        ? "font-size: {$fontSize}; font-weight: 600; color: {$color}; line-height: 1.2; text-align: center; text-wrap: balance; max-width: 100%; padding: 0 0.5rem;"
        : "font-size: {$fontSize}; font-weight: 600; color: {$color}; white-space: nowrap; line-height: 1.2;";
@endphp

<div {{ $attributes->merge(['style' => $rowStyle]) }}>
    <img
        src="{{ asset('images/onpoint-call.webp') }}"
        alt="{{ config('app.name') }}"
        style="height: {{ $imgHeight }}; width: auto; flex-shrink: 0; border-radius: 0.5rem;"
    >
    <span style="{{ $nameStyle }}">
        {{ config('app.name') }}
    </span>
</div>
