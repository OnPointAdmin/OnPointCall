{{-- Compact read-only record, used by the sidebar Lookup tab. --}}
<div>
    <p class="m-0 mb-2 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Read-only record</p>

    <p class="m-0 text-[22px] font-extrabold text-slate-900 dark:text-slate-100">{{ $lead->phone }}</p>
    <p class="m-0 mt-1 select-none text-sm font-semibold text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->fullName() }}</p>
    <p class="m-0 mt-0.5 select-none text-xs text-slate-500 dark:text-slate-400" oncopy="return false">{{ $lead->city }}, {{ $lead->state }}</p>

    <span class="mt-2 inline-block rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-1 text-xs font-bold text-slate-600 dark:text-slate-300">
        {{ $lead->status->label() }}
    </span>

    @if ($lead->email)
        <p class="m-0 mt-3 text-xs font-semibold text-slate-400 dark:text-slate-500">Email</p>
        <p class="m-0 mt-0.5 select-none overflow-wrap-anywhere text-sm text-slate-700 dark:text-slate-300" oncopy="return false">{{ $lead->email }}</p>
    @endif

    @if ($lead->last_call_at)
        <p class="m-0 mt-3 text-xs font-semibold text-slate-400 dark:text-slate-500">Last call</p>
        <p class="m-0 mt-0.5 text-sm text-slate-700 dark:text-slate-300">{{ $lead->last_call_at->format('M j, g:i A') }}</p>
    @endif
</div>
