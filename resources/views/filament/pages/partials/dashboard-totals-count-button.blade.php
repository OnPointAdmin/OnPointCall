@php
    $count = (int) ($count ?? 0);
    $metricKey = $metricKey ?? '';
    $kind = $kind ?? 'metric';
    $label = $label ?? '';
    $dispositionSlug = $dispositionSlug ?? null;
    $reasonLabel = $reasonLabel ?? null;
@endphp

@if ($count > 0)
    <button
        type="button"
        class="dashboard-totals-drilldown"
        wire:click="openTotalsLeads(@js($kind), @js($metricKey), @js($label), @js($dispositionSlug), @js($reasonLabel))"
    >
        {{ number_format($count) }}
    </button>
@else
    {{ number_format($count) }}
@endif
