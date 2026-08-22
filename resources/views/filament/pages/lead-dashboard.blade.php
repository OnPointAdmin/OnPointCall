<x-filament-panels::page>
    @php
        $snapshot = $this->snapshot();
        $statusOrder = ['holding', 'callable', 'callback', 'booked', 'terminal', 'dnc'];
    @endphp

    <div class="dashboard-report">
        <div class="dashboard-header">
            <h1>Lead Dashboard</h1>

            <div class="dashboard-header-meta">
                <p class="dashboard-run-date">
                    As of: <span>{{ $snapshot->runAt }} {{ $snapshot->timezone }}</span>
                </p>

                <button
                    type="button"
                    wire:click="refreshSnapshot"
                    wire:loading.attr="disabled"
                    class="dashboard-refresh"
                >
                    <svg wire:loading.remove wire:target="refreshSnapshot" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    Refresh
                </button>
            </div>
        </div>

        <div class="dashboard-card dashboard-totals">
            <h2 class="dashboard-section-title">Lead status</h2>

            <div class="dashboard-stat-stack">
                <div class="dashboard-stat-card">
                    <p class="dashboard-stat-label">Total leads</p>
                    <p class="dashboard-stat-value">{{ number_format($snapshot->total) }}</p>
                    <p class="dashboard-stat-percent"></p>
                </div>
                <div class="dashboard-stat-card">
                    <p class="dashboard-stat-label">Fresh</p>
                    <p class="dashboard-stat-value">{{ number_format($snapshot->fresh) }}</p>
                    <p class="dashboard-stat-percent">Never dialed</p>
                </div>

                @foreach ($statusOrder as $status)
                    <div class="dashboard-stat-card">
                        <p class="dashboard-stat-label">{{ $this->statusLabel($status) }}</p>
                        <p class="dashboard-stat-value">{{ number_format($snapshot->statusCounts[$status] ?? 0) }}</p>
                        <p class="dashboard-stat-percent"></p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="dashboard-card dashboard-totals">
            <h2 class="dashboard-section-title">Dialable now</h2>

            <div class="dashboard-stat-stack">
                <div class="dashboard-stat-card">
                    <p class="dashboard-stat-label">Ready now</p>
                    <p class="dashboard-stat-value">{{ number_format($snapshot->readyNow) }}</p>
                    <p class="dashboard-stat-percent">In legal hours and cadence</p>
                </div>
                <div class="dashboard-stat-card">
                    <p class="dashboard-stat-label">Waiting</p>
                    <p class="dashboard-stat-value">{{ number_format($snapshot->waiting) }}</p>
                    <p class="dashboard-stat-percent">Cadence, hours, or claimed</p>
                </div>
                <div class="dashboard-stat-card">
                    <p class="dashboard-stat-label">Claimed</p>
                    <p class="dashboard-stat-value">{{ number_format($snapshot->claimed) }}</p>
                    <p class="dashboard-stat-percent">On an active lease</p>
                </div>
                <div class="dashboard-stat-card">
                    <p class="dashboard-stat-label">Exhausted</p>
                    <p class="dashboard-stat-value">{{ number_format($snapshot->exhausted) }}</p>
                    <p class="dashboard-stat-percent">At max attempts</p>
                </div>
                <div class="dashboard-stat-card">
                    <p class="dashboard-stat-label">Callbacks due</p>
                    <p class="dashboard-stat-value">{{ number_format($snapshot->callbacksDue) }}</p>
                    <p class="dashboard-stat-percent">Scheduled time has passed</p>
                </div>
                <div class="dashboard-stat-card">
                    <p class="dashboard-stat-label">Callbacks scheduled</p>
                    <p class="dashboard-stat-value">{{ number_format($snapshot->callbacksScheduled) }}</p>
                    <p class="dashboard-stat-percent">Still in the future</p>
                </div>
            </div>
        </div>

        <div class="dashboard-card dashboard-totals">
            <h2 class="dashboard-section-title">When leads become dialable</h2>

            <div class="dashboard-stat-stack">
                @foreach ($snapshot->forecast as $bucket)
                    <div class="dashboard-stat-card">
                        <p class="dashboard-stat-label">{{ $bucket['label'] }}</p>
                        <p class="dashboard-stat-value">{{ number_format($bucket['count']) }}</p>
                        <p class="dashboard-stat-percent"></p>
                    </div>
                @endforeach
            </div>

            <p class="dashboard-footnote">
                Forecast is for the callable pool only (not holding, booked, terminal, DNC, or callbacks). Times use {{ $snapshot->timezone }}.
            </p>
        </div>

        <div class="dashboard-card dashboard-table">
            <h2 class="dashboard-section-title">By calling list</h2>

            <div class="dashboard-table-scroll">
                <table class="lead-dashboard-table">
                    <thead>
                        <tr>
                            <th class="col-start">List</th>
                            <th>Total</th>
                            <th>Holding</th>
                            <th>Ready now</th>
                            <th>Waiting</th>
                            <th>Callbacks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($snapshot->byList as $row)
                            <tr>
                                <td class="col-start">{{ $row['name'] }}</td>
                                <td>{{ number_format($row['total']) }}</td>
                                <td>{{ number_format($row['holding']) }}</td>
                                <td>{{ number_format($row['ready_now']) }}</td>
                                <td>{{ number_format($row['waiting']) }}</td>
                                <td>{{ number_format($row['callbacks']) }}</td>
                            </tr>
                        @empty
                            <tr class="empty-row">
                                <td colspan="6">No calling lists yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
