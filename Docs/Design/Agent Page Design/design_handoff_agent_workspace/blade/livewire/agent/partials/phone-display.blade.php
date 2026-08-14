{{-- Hero phone block: the one thing an agent needs before dialing. --}}
<div class="border-b border-slate-100 dark:border-slate-800 px-5 py-8 text-center">
    <p class="m-0 mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Phone number</p>

    <button
        type="button"
        wire:click="copyPhone"
        class="block w-full border-none bg-transparent p-0 font-extrabold leading-tight tracking-tight text-slate-900 dark:text-slate-100"
        style="font-size: clamp(34px, 8vw, 56px);"
    >
        {{ $lead->phone }}
    </button>
    <p class="m-0 mt-2 text-sm text-slate-400 dark:text-slate-500">Click to copy phone number</p>

    <p class="m-0 mt-2.5 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $lead->fullName() }}</p>

    @if ($manualDialOnly)
        <p class="m-0 mt-1.5 inline-block rounded-full bg-amber-100 dark:bg-amber-500/15 px-2.5 py-1 text-xs font-bold text-amber-800 dark:text-amber-400">
            Manual dial only — hand-dial this number
        </p>
    @endif

    @if ($lead->phone2 || $lead->first_name_2 || $lead->last_name_2)
        <p class="m-0 mt-2.5 text-sm text-slate-500 dark:text-slate-400">
            Secondary contact:
            <span class="select-none font-semibold text-slate-700 dark:text-slate-300" oncopy="return false">{{ trim($lead->first_name_2 . ' ' . $lead->last_name_2) }}</span>
            &middot;
            <span class="select-none font-semibold text-slate-700 dark:text-slate-300" oncopy="return false">{{ $lead->phone2 }}</span>
        </p>
    @endif
</div>
