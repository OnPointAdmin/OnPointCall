@php
    /** @var \App\Models\Lead $lead */
@endphp

<div class="space-y-6 text-sm">
    <div class="grid gap-2 sm:grid-cols-2">
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Status</p>
            <p class="font-semibold">{{ $lead->rnd_status?->label() ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Checked at</p>
            <p>{{ \App\Support\CompanyTimezone::display($lead->rnd_checked_at, format: 'M j, Y g:i A T') ?: '—' }}</p>
        </div>
    </div>

    @if ($lead->rnd_last_error)
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-danger-800 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300">
            <p class="text-xs font-semibold uppercase tracking-wide">Error</p>
            <p class="mt-1 whitespace-pre-wrap">{{ $lead->rnd_last_error }}</p>
        </div>
    @endif
</div>
