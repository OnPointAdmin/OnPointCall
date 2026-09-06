<x-filament-panels::page>
    @php
        $report = $this->report ?? ['totals' => ['total_leads_called' => 0, 'booked' => 0, 'booked_percent' => null], 'rows' => []];
        $totals = $report['totals'] ?? [];
        $rows = $report['rows'] ?? [];
        $groupBy = $this->reportGroupBy();
        $columnCount = ($groupBy->showsVenue() ? 1 : 0) + ($groupBy->showsEvent() ? 1 : 0) + 3;
    @endphp

    <div class="dashboard-report">
        <div class="dashboard-header">
            <h1>Performance by Lead Source</h1>

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
                <div class="dashboard-stat-card">
                    <p class="dashboard-stat-label">Total Leads Called</p>
                    <p class="dashboard-stat-value">{{ number_format($totals['total_leads_called'] ?? 0) }}</p>
                    <p class="dashboard-stat-percent"></p>
                </div>

                <div class="dashboard-stat-card">
                    <p class="dashboard-stat-label">Booked</p>
                    <p class="dashboard-stat-value">{{ number_format($totals['booked'] ?? 0) }}</p>
                    <p class="dashboard-stat-percent"></p>
                </div>

                <div class="dashboard-stat-card">
                    <p class="dashboard-stat-label">Booked %</p>
                    <p class="dashboard-stat-value">{{ $this->formatPercent($totals['booked_percent'] ?? null) }}</p>
                    <p class="dashboard-stat-percent"></p>
                </div>
            </div>

            <p class="dashboard-footnote">
                Booked % is the share of total leads called that were booked.
            </p>
        </div>

        <div class="dashboard-card dashboard-table">
            <h2 class="dashboard-section-title">{{ $groupBy->tableTitle() }}</h2>

            <div class="dashboard-table-scroll">
                <table>
                    <thead>
                        <tr>
                            @if ($groupBy->showsVenue())
                                <th class="col-start">Venue</th>
                            @endif
                            @if ($groupBy->showsEvent())
                                <th @class(['col-start' => ! $groupBy->showsVenue()])>Event</th>
                            @endif
                            <th @class(['col-start' => ! $groupBy->showsVenue() && ! $groupBy->showsEvent()])>Total Leads Called</th>
                            <th>Booked</th>
                            <th>Booked %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                @if ($groupBy->showsVenue())
                                    <td class="col-start">{{ $row['venue'] }}</td>
                                @endif
                                @if ($groupBy->showsEvent())
                                    <td @class(['col-start' => ! $groupBy->showsVenue()])>{{ $row['event'] }}</td>
                                @endif
                                <td @class(['col-start' => ! $groupBy->showsVenue() && ! $groupBy->showsEvent()])>{{ number_format($row['total_leads_called']) }}</td>
                                <td>{{ number_format($row['booked']) }}</td>
                                <td>{{ $this->formatPercent($row['booked_percent'] ?? null) }}</td>
                            </tr>
                        @empty
                            <tr class="empty-row">
                                <td colspan="{{ $columnCount }}">
                                    No activity for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="dashboard-footnote">
                Rows are ranked by booked count
                @if ($groupBy->showsVenue() && $groupBy->showsEvent())
                    , then venue, then event
                @elseif ($groupBy->showsVenue())
                    , then venue
                @else
                    , then event
                @endif
                . Blank values appear as (none).
            </p>
        </div>
    </div>
</x-filament-panels::page>
