<div class="rounded-md border border-slate-300 bg-slate-50 p-4">
    @if (in_array($lead->status->value, ['booked', 'terminal', 'dnc']))
        <div class="mb-3 rounded-md border border-red-300 bg-red-100 p-2 text-sm font-semibold text-red-800">
            {{ $lead->status->label() }} — read only
        </div>
    @elseif ($lead->status->value === 'callback' && $lead->callback_owner_id !== auth()->id())
        <div class="mb-3 rounded-md border border-amber-300 bg-amber-100 p-2 text-sm font-semibold text-amber-900">
            Callback owned by another agent — read only
        </div>
    @endif

    <p class="text-lg font-semibold">{{ $lead->fullName() ?: 'Unknown' }}</p>
    <p class="text-slate-600">{{ $lead->phone }}</p>
    <p class="mt-2 text-sm text-slate-600">{{ $lead->city }}, {{ $lead->state }} — {{ $lead->status->label() }}</p>

    @php
        $lastDisposition = $lead->history->first(fn ($h) => $h->event_type->value === 'disposition');
    @endphp
    @if ($lastDisposition)
        <p class="mt-2 text-sm text-slate-600">
            Last disposition: {{ $lastDisposition->payload['disposition'] ?? '—' }}
            ({{ $lastDisposition->occurred_at?->format('M j, g:i A') }})
        </p>
    @endif
</div>
