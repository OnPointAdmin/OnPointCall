@php
    $item = $item ?? [];
    $metricKey = $metricKey ?? '';
    $metricDefinitions = $metricDefinitions ?? [];
    $rowClass = $rowClass ?? 'list-row';
    $parentDispositionSlug = $parentDispositionSlug ?? ($item['slug'] ?? null);
    $percent = $item['percent'] ?? null;
    $count = (int) ($item['count'] ?? 0);
    $kind = $item['kind'] ?? 'disposition';
    $label = $item['label'] ?? '';
    $dispositionSlug = $kind === 'reason' ? $parentDispositionSlug : ($item['slug'] ?? null);
    $reasonLabel = $kind === 'reason' ? $label : null;
    $drilldownLabel = $label;
@endphp

<tr class="{{ $rowClass }}" x-show="expandedKeys.includes('{{ $metricKey }}')" x-cloak>
    <td class="col-start">
        @if ($count > 0)
            <button
                type="button"
                class="dashboard-totals-drilldown dashboard-totals-drilldown-label"
                wire:click="openTotalsLeads(@js($kind), @js($metricKey), @js($drilldownLabel), @js($dispositionSlug), @js($reasonLabel))"
            >
                {{ $label }}
            </button>
        @else
            {{ $label }}
        @endif
    </td>
    <td></td>
    @foreach ($metricDefinitions as $definition)
        @continue($definition['key'] === 'total_leads_called')

        @if ($definition['key'] === $metricKey)
            <td class="split breakdown-fill">
                @include('filament.pages.partials.dashboard-totals-count-button', [
                    'count' => $count,
                    'metricKey' => $metricKey,
                    'kind' => $kind,
                    'label' => $drilldownLabel,
                    'dispositionSlug' => $dispositionSlug,
                    'reasonLabel' => $reasonLabel,
                ])
            </td>
            <td class="muted breakdown-fill">{{ $percent === null ? '—' : number_format($percent, 1).'%' }}</td>
        @else
            <td class="split"></td>
            <td></td>
        @endif
    @endforeach
</tr>
