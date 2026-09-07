@php
    $item = $item ?? [];
    $metricKey = $metricKey ?? '';
    $metricDefinitions = $metricDefinitions ?? [];
    $rowClass = $rowClass ?? 'list-row';
    $percent = $item['percent'] ?? null;
@endphp

<tr class="{{ $rowClass }}" x-show="expandedKeys.includes('{{ $metricKey }}')" x-cloak>
    <td class="col-start"></td>
    <td></td>
    @foreach ($metricDefinitions as $definition)
        @continue($definition['key'] === 'total_leads_called')

        @if ($definition['key'] === $metricKey)
            <td class="split breakdown-fill" colspan="2">
                <div class="dashboard-breakdown-cell">
                    <span class="dashboard-breakdown-label">{{ $item['label'] ?? '' }}</span>
                    <span class="dashboard-breakdown-stats">
                        {{ number_format($item['count'] ?? 0) }}
                        <span class="muted">{{ $percent === null ? '—' : number_format($percent, 1).'%' }}</span>
                    </span>
                </div>
            </td>
        @else
            <td class="split"></td>
            <td></td>
        @endif
    @endforeach
</tr>
