{{-- Compact read-only record, used by the sidebar Lookup tab. --}}
<div>
    <p class="m-0 mb-2 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Read-only record</p>

    <p class="m-0 text-[22px] font-extrabold text-slate-900 dark:text-slate-100">{{ \App\Support\PhoneNormalizer::formatForDisplay($lead->phone) ?? $lead->phone }}</p>
    <p class="m-0 mt-1 select-none text-sm font-semibold text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->fullName() ?: 'Unknown' }}</p>
    <p class="m-0 mt-0.5 select-none text-xs text-slate-500 dark:text-slate-400" oncopy="return false">{{ $lead->city }}, {{ $lead->state }}</p>

    <span class="mt-2 inline-block rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
        {{ $lead->status->label() }}
    </span>

    @if (in_array($lead->status->value, ['booked', 'terminal', 'dnc'], true))
        <div class="mt-3 rounded-md border border-red-300 bg-red-50 p-2 text-sm font-semibold text-red-800 dark:border-red-800 dark:bg-red-500/10 dark:text-red-400">
            {{ $lead->status->label() }} — read only
        </div>
    @elseif ($lead->status->value === 'callback' && $lead->callback_owner_id !== auth('agent')->id())
        <div class="mt-3 rounded-md border border-amber-300 bg-amber-50 p-2 text-sm font-semibold text-amber-900 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-400">
            Callback owned by another agent — read only
        </div>
    @endif

    @if ($lead->email)
        <p class="m-0 mt-3 text-xs font-semibold text-slate-400 dark:text-slate-500">Email</p>
        <p class="m-0 mt-0.5 select-none break-words text-sm text-slate-700 dark:text-slate-300" oncopy="return false">{{ $lead->email }}</p>
    @endif

    @if ($lead->last_attempt_at)
        <p class="m-0 mt-3 text-xs font-semibold text-slate-400 dark:text-slate-500">Last call</p>
        <p class="m-0 mt-0.5 text-sm text-slate-700 dark:text-slate-300">{{ $lead->last_attempt_at->format('M j, g:i A') }}</p>
    @endif

    @php
        $lastDisposition = $lead->history->first(fn ($h) => $h->event_type->value === 'disposition');
    @endphp
    @if ($lastDisposition)
        <p class="m-0 mt-3 text-xs font-semibold text-slate-400 dark:text-slate-500">Last disposition</p>
        <p class="m-0 mt-0.5 text-sm text-slate-700 dark:text-slate-300">
            {{ $lastDisposition->payload['disposition'] ?? '—' }}
            ({{ $lastDisposition->occurred_at?->format('M j, g:i A') }})
        </p>
    @endif
</div>
