@php
    /** @var \App\Models\Lead $lead */
@endphp

<div class="space-y-6 text-sm">
    <div class="grid gap-2 sm:grid-cols-3">
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Status</p>
            <p class="font-semibold">{{ $lead->soft_score_status?->label() ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Code</p>
            <p class="font-semibold">{{ $lead->soft_score_code ?: '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Checked at</p>
            <p>{{ \App\Support\CompanyTimezone::display($lead->soft_score_checked_at, format: 'M j, Y g:i A T') ?: '—' }}</p>
        </div>
    </div>

    @if ($lead->soft_score_last_error)
        @include('filament.partials.check-error', ['message' => $lead->soft_score_last_error])
    @endif
</div>
