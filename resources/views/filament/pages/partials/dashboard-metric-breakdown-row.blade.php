@php
    $item = $item ?? [];
    $metricKey = $metricKey ?? '';
    $metricDefinitions = $metricDefinitions ?? [];
    $rowClass = $rowClass ?? 'list-row';
    $percent = $item['percent'] ?? null;
@endphp

<tr class="{{ $rowClass }}" x-show="expandedKeys.includes('{{ $metricKey }}')" x-cloak>
    <td class="col-start">{{ $item['label'] ?? '' }}</td>
    <td></td>
    @foreach ($metricDefinitions as $definition)
        @continue($definition['key'] === 'total_leads_called')

        @if ($definition['key'] === $metricKey)
            <td class="split breakdown-fill">{{ number_format($item['count'] ?? 0) }}</td>
            <td class="muted breakdown-fill">{{ $percent === null ? '—' : number_format($percent, 1).'%' }}</td>
        @else
            <td class="split"></td>
            <td></td>
        @endif
    @endforeach
</tr>
