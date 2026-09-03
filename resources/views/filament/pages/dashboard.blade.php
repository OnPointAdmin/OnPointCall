<x-filament-panels::page>
    @php
        $report = $this->report ?? ['totals' => [], 'agents' => []];
        $totals = $report['totals'] ?? [];
        $agents = $report['agents'] ?? [];
        $metricDefinitions = $this->metricDefinitions();
        $multiListAgentIds = collect($agents)
            ->filter(fn (array $agent): bool => count($agent['lists'] ?? []) > 1)
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    @endphp

    <div class="dashboard-report">
        <div class="dashboard-header">
            <h1>{{ $this->dashboardTitle() }}</h1>

            <div class="dashboard-header-meta">
                @if ($this->runAt)
                    <p class="dashboard-run-date">
                        Run Date: <span>{{ $this->runAt }}</span>
                    </p>
                @endif

                <button
                    type="button"
                    wire:click="refreshReport"
                    wire:loading.attr="disabled"
                    class="dashboard-refresh"
                >
                    <svg wire:loading.remove wire:target="refreshReport" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    Refresh
                </button>
            </div>
        </div>

        <div class="dashboard-card dashboard-filters">
            <div class="dashboard-presets" role="group" aria-label="Date presets">
                @foreach ($this->datePresets() as $preset)
                    <button
                        type="button"
                        wire:click="applyPreset('{{ $preset['key'] }}')"
                        class="dashboard-preset{{ $this->datePreset === $preset['key'] ? ' is-active' : '' }}"
                    >
                        {{ $preset['label'] }}
                    </button>
                @endforeach
            </div>

            {{ $this->content }}
        </div>

        <div class="dashboard-card dashboard-totals">
            <h2 class="dashboard-section-title">Totals</h2>

            <div class="dashboard-stat-row">
                @foreach ($metricDefinitions as $definition)
                    @php
                        $metric = $totals[$definition['key']] ?? ['count' => 0, 'percent' => null];
                    @endphp

                    <div class="dashboard-stat-card">
                        <p class="dashboard-stat-label">{{ $definition['label'] }}</p>
                        <p class="dashboard-stat-value">{{ number_format($metric['count'] ?? 0) }}</p>
                        <p class="dashboard-stat-percent">
                            @if ($definition['show_percent'])
                                {{ $this->formatPercent($totals, $definition['key']) }}
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>

            <p class="dashboard-footnote">
                Percentages represent % of total leads called.
            </p>
        </div>

        <div
            class="dashboard-card dashboard-table"
            x-data="{
                expandedIds: [],
                multiIds: {{ json_encode($multiListAgentIds) }},
                toggle(id) {
                    const index = this.expandedIds.indexOf(id);
                    if (index === -1) {
                        this.expandedIds.push(id);
                    } else {
                        this.expandedIds.splice(index, 1);
                    }
                },
                expandAll() {
                    this.expandedIds = [...this.multiIds];
                },
                collapseAll() {
                    this.expandedIds = [];
                }
            }"
        >
            <div class="dashboard-section-header">
                <h2 class="dashboard-section-title">Results by Rep</h2>

                <div class="dashboard-expand-actions" x-show="multiIds.length > 0" x-cloak>
                    <button type="button" class="dashboard-expand-btn" x-on:click="expandAll()">Expand all</button>
                    <button type="button" class="dashboard-expand-btn" x-on:click="collapseAll()">Collapse all</button>
                </div>
            </div>

            <div class="dashboard-table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2" class="col-start">Rep</th>
                            <th rowspan="2">Total Leads Called</th>
                            @foreach ($metricDefinitions as $definition)
                                @continue($definition['key'] === 'total_leads_called')

                                <th colspan="2" class="split">{{ $definition['label'] }}</th>
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
                    <tbody>
                        @forelse ($agents as $agent)
                            @php
                                $agentId = (int) $agent['user_id'];
                                $agentLists = $agent['lists'] ?? [];
                                $canExpand = count($agentLists) > 1;
                            @endphp
                            <tr>
                                <td class="col-start">
                                    @if ($canExpand)
                                        <button
                                            type="button"
                                            class="dashboard-rep-toggle"
                                            x-on:click="toggle({{ $agentId }})"
                                            :aria-expanded="expandedIds.includes({{ $agentId }})"
                                        >
                                            <svg class="dashboard-rep-chevron" :class="{ 'is-open': expandedIds.includes({{ $agentId }}) }" width="12" height="12" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 5l6 5-6 5" />
                                            </svg>
                                            {{ $agent['name'] }}
                                        </button>
                                    @else
                                        {{ $agent['name'] }}
                                    @endif
                                </td>
                                <td>{{ number_format($agent['metrics']['total_leads_called']['count'] ?? 0) }}</td>
                                @foreach ($metricDefinitions as $definition)
                                    @continue($definition['key'] === 'total_leads_called')

                                    @php
                                        $metric = $agent['metrics'][$definition['key']] ?? ['count' => 0, 'percent' => null];
                                    @endphp

                                    <td class="split">{{ number_format($metric['count'] ?? 0) }}</td>
                                    <td class="muted">{{ $this->formatPercent($agent['metrics'], $definition['key']) }}</td>
                                @endforeach
                            </tr>
                            @if ($canExpand)
                                @foreach ($agentLists as $list)
                                    <tr class="list-row" x-show="expandedIds.includes({{ $agentId }})" x-cloak>
                                        <td class="col-start">{{ $list['name'] }}</td>
                                        <td>{{ number_format($list['metrics']['total_leads_called']['count'] ?? 0) }}</td>
                                        @foreach ($metricDefinitions as $definition)
                                            @continue($definition['key'] === 'total_leads_called')

                                            @php
                                                $metric = $list['metrics'][$definition['key']] ?? ['count' => 0, 'percent' => null];
                                            @endphp

                                            <td class="split">{{ number_format($metric['count'] ?? 0) }}</td>
                                            <td class="muted">{{ $this->formatPercent($list['metrics'], $definition['key']) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endif
                        @empty
                            <tr class="empty-row">
                                <td colspan="{{ 2 + ((count($metricDefinitions) - 1) * 2) }}">
                                    No activity for the selected filters.
                                </td>
                            </tr>
                        @endforelse

                        @if (count($agents) > 0)
                            <tr class="total-row">
                                <td class="col-start">Total</td>
                                <td>{{ number_format($totals['total_leads_called']['count'] ?? 0) }}</td>
                                @foreach ($metricDefinitions as $definition)
                                    @continue($definition['key'] === 'total_leads_called')

                                    @php
                                        $metric = $totals[$definition['key']] ?? ['count' => 0, 'percent' => null];
                                    @endphp

                                    <td class="split">{{ number_format($metric['count'] ?? 0) }}</td>
                                    <td>{{ $this->formatPercent($totals, $definition['key']) }}</td>
                                @endforeach
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <p class="dashboard-footnote">
                Percentages represent % of total leads called.
            </p>
        </div>

        @php
            $queueStatuses = $this->queueStatuses();
        @endphp

        <div class="dashboard-card dashboard-table dashboard-queue-status">
            <h2 class="dashboard-section-title">Queue status</h2>

            <div class="dashboard-table-scroll">
                <table class="dashboard-queue-table">
                    <thead>
                        <tr>
                            <th class="col-start">List</th>
                            <th>Ready now</th>
                            <th>Waiting on cadence</th>
                            <th class="dashboard-queue-timing">Cadence timing</th>
                            <th>Waiting on legal hours</th>
                            <th>On an active claim</th>
                            <th>At max attempts</th>
                            <th>Callbacks due now</th>
                            <th>Callbacks scheduled</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($queueStatuses as $entry)
                            @php
                                $list = $entry['list'];
                                $inventory = $entry['inventory'];
                            @endphp

                            <tr>
                                <td class="col-start">
                                    <a
                                        href="{{ \App\Filament\Resources\CallingLists\CallingListResource::getUrl('view', ['record' => $list]) }}"
                                        class="dashboard-queue-list-link"
                                    >
                                        {{ $list->name }}
                                    </a>
                                </td>
                                <td>{{ number_format($inventory->readyNow) }}</td>
                                <td>{{ number_format($inventory->waitingCadence) }}</td>
                                <td class="dashboard-queue-timing">
                                    @if ($inventory->waitingCadence > 0)
                                        <div class="dashboard-queue-timing-list">
                                            @foreach ($inventory->cadenceWaitSlots as $slot)
                                                @if ($slot['count'] > 0)
                                                    <div>{{ number_format($slot['count']) }} {{ $slot['label'] }}</div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ number_format($inventory->waitingHours) }}</td>
                                <td>{{ number_format($inventory->claimed) }}</td>
                                <td>{{ number_format($inventory->maxAttempts) }}</td>
                                <td>{{ number_format($inventory->callbacksDue) }}</td>
                                <td>{{ number_format($inventory->callbacksScheduled) }}</td>
                            </tr>
                        @empty
                            <tr class="empty-row">
                                <td colspan="9">No lists have been dialed today.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (count($queueStatuses) > 0)
                <p class="dashboard-footnote">
                    Live remaining inventory for lists dialed today or with an active claim. Times use {{ $queueStatuses[0]['inventory']->timezone }}.
                </p>
            @endif
        </div>
    </div>
</x-filament-panels::page>
