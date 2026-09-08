@php
    use App\Filament\Resources\Leads\LeadResource;
    use App\Models\DispositionDefinition;
@endphp

<div class="dashboard-totals-leads-modal">
    <table class="dashboard-totals-leads-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Last Disp</th>
                <th>List</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($leads as $lead)
                <tr>
                    <td>
                        <a
                            href="{{ LeadResource::getUrl('view', ['record' => $lead]) }}"
                            class="dashboard-totals-leads-link"
                        >
                            {{ $lead->fullName() ?: '—' }}
                        </a>
                    </td>
                    <td>
                        <a
                            href="{{ LeadResource::getUrl('view', ['record' => $lead]) }}"
                            class="dashboard-totals-leads-link"
                        >
                            {{ $lead->phone }}
                        </a>
                    </td>
                    <td>{{ $lead->status?->label() ?? '—' }}</td>
                    <td>
                        @php
                            $dispositionSlug = $lead->latestDisposition?->payload['disposition'] ?? null;
                            $dispositionLabel = is_string($dispositionSlug) && $dispositionSlug !== ''
                                ? (DispositionDefinition::labelForSlug($lead->company_id, $dispositionSlug) ?? $dispositionSlug)
                                : null;
                        @endphp
                        {{ $dispositionLabel ?? '—' }}
                    </td>
                    <td>{{ $lead->callingList?->name ?? 'Holding' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="dashboard-totals-leads-empty">No matching leads.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($leads->hasPages())
        <div class="dashboard-totals-leads-pagination">
            <button
                type="button"
                class="dashboard-totals-leads-page-btn"
                wire:click="gotoTotalsLeadsPage({{ $leads->currentPage() - 1 }})"
                @disabled($leads->onFirstPage())
            >
                Previous
            </button>

            <span class="dashboard-totals-leads-page-info">
                Page {{ $leads->currentPage() }} of {{ $leads->lastPage() }}
            </span>

            <button
                type="button"
                class="dashboard-totals-leads-page-btn"
                wire:click="gotoTotalsLeadsPage({{ $leads->currentPage() + 1 }})"
                @disabled(!$leads->hasMorePages())
            >
                Next
            </button>
        </div>
    @endif
</div>
