@php
    $firstColumnLabel = $firstColumnLabel ?? '';
    $metricDefinitions = $metricDefinitions ?? [];
    $breakdowns = $breakdowns ?? [];
    $expandable = $expandable ?? false;
@endphp

<thead>
    <tr>
        <th rowspan="2" class="col-start">{{ $firstColumnLabel }}</th>
        <th rowspan="2">Total</th>
        @foreach ($metricDefinitions as $definition)
            @continue($definition['key'] === 'total_leads_called')

            @php
                $metricKey = $definition['key'];
                $canExpand = $expandable && count($breakdowns[$metricKey] ?? []) > 1;
            @endphp

            <th colspan="2" class="split">
                @if ($canExpand)
                    <button
                        type="button"
                        class="dashboard-rep-toggle dashboard-metric-toggle"
                        x-on:click="toggle('{{ $metricKey }}')"
                        :aria-expanded="expandedKeys.includes('{{ $metricKey }}')"
                    >
                        <svg class="dashboard-rep-chevron" :class="{ 'is-open': expandedKeys.includes('{{ $metricKey }}') }" width="12" height="12" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 5l6 5-6 5" />
                        </svg>
                        {{ $definition['label'] }}
                    </button>
                @else
                    {{ $definition['label'] }}
                @endif
            </th>
        @endforeach
    </tr>
    <tr>
        @foreach ($metricDefinitions as $definition)
            @continue($definition['key'] === 'total_leads_called')

            <th class="split">#</th>
            <th>%</th>
        @endforeach
    </tr>
</thead>
