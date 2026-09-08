<x-filament-panels::page>
    @php
        $calls = $this->callRows();
        $columns = $this->visibleColumnDefs();
        $columnCount = max(1, count($columns));
    @endphp

    <div class="dashboard-report">
        <div class="dashboard-header">
            <h1>Call Detail</h1>

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

                <div
                    class="dashboard-columns"
                    x-data
                    @keydown.escape.window="$wire.set('columnsMenuOpen', false)"
                    @click.outside="$wire.set('columnsMenuOpen', false)"
                >
                    <button
                        type="button"
                        class="dashboard-refresh"
                        wire:click="$toggle('columnsMenuOpen')"
                        aria-haspopup="true"
                        aria-expanded="{{ $this->columnsMenuOpen ? 'true' : 'false' }}"
                    >
                        Columns
                    </button>

                    @if ($this->columnsMenuOpen)
                        <div class="dashboard-columns-menu">
                            <div class="dashboard-columns-menu-header">
                                <span>Show columns</span>
                                <button type="button" class="dashboard-columns-reset" wire:click="resetColumns">
                                    Reset
                                </button>
                            </div>

                            @foreach ($this->columnOptions() as $key => $label)
                                <label class="dashboard-columns-option">
                                    <input
                                        type="checkbox"
                                        value="{{ $key }}"
                                        wire:model.live="visibleColumns"
                                    >
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                <button
                    type="button"
                    wire:click="exportCsv"
                    wire:loading.attr="disabled"
                    class="dashboard-refresh"
                >
                    Export CSV
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

        <div class="dashboard-card dashboard-table">
            <div class="dashboard-section-header">
                <h2 class="dashboard-section-title">Leads called</h2>
                <p class="dashboard-run-date" style="margin: 0; color: #64748b;">
                    {{ number_format($calls->total()) }} {{ $calls->total() === 1 ? 'call' : 'calls' }}
                </p>
            </div>

            <div class="dashboard-table-scroll">
                <table>
                    <thead>
                        <tr>
                            @foreach ($columns as $index => $column)
                                <th @class(['col-start' => $index === 0, 'col-wrap' => $column['wrap']])>
                                    {{ $column['label'] }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($calls as $row)
                            <tr>
                                @foreach ($columns as $index => $column)
                                    @php
                                        $value = trim((string) ($row[$column['key']] ?? ''));
                                        $display = $value !== '' ? $value : '—';
                                        $linked = $this->cellIsLinked($column['key']) && $row['lead_id'];
                                    @endphp
                                    <td @class(['col-start' => $index === 0, 'col-wrap' => $column['wrap']])>
                                        @if ($linked)
                                            <a href="{{ $this->leadUrl($row['lead_id']) }}" class="dashboard-totals-leads-link">
                                                {{ $display }}
                                            </a>
                                        @else
                                            {{ $display }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr class="empty-row">
                                <td colspan="{{ $columnCount }}">
                                    No calls for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($calls->hasPages())
                <div class="dashboard-totals-leads-pagination" style="margin-top: 1rem;">
                    <button
                        type="button"
                        class="dashboard-totals-leads-page-btn"
                        wire:click="gotoCallsPage({{ $calls->currentPage() - 1 }})"
                        @disabled($calls->onFirstPage())
                    >
                        Previous
                    </button>

                    <span class="dashboard-totals-leads-page-info">
                        Page {{ $calls->currentPage() }} of {{ $calls->lastPage() }}
                    </span>

                    <button
                        type="button"
                        class="dashboard-totals-leads-page-btn"
                        wire:click="gotoCallsPage({{ $calls->currentPage() + 1 }})"
                        @disabled(!$calls->hasMorePages())
                    >
                        Next
                    </button>
                </div>
            @endif

            <p class="dashboard-footnote">
                One row per call in the date range. A lead called more than once appears more than once. Use Columns to add qualified partners, demographics, Soft Score, and other lead fields. Export CSV matches the selected columns.
            </p>
        </div>
    </div>
</x-filament-panels::page>
