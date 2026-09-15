@php
    /** @var string $message */
    $parsed = \App\Support\CheckErrorFormatter::parse($message);
    $label = $label ?? null;
    $total = $total ?? null;
    $looksLikeJson = str_starts_with(ltrim($parsed['message']), '{') || str_starts_with(ltrim($parsed['message']), '[');
@endphp

<div class="rounded-lg border border-danger-300 bg-danger-50 p-4 text-danger-800 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300">
    @if (filled($label))
        <p class="text-xs font-semibold uppercase tracking-wide">{{ $label }}</p>
    @endif

    @if (filled($parsed['summary']) || filled($parsed['code']))
        <div class="{{ filled($label) ? 'mt-2' : '' }} flex flex-wrap items-center gap-2">
            @if (filled($parsed['summary']))
                <p class="font-semibold">{{ $parsed['summary'] }}</p>
            @endif
            @if (filled($parsed['code']))
                <span class="inline-flex rounded-md bg-danger-100 px-2 py-0.5 font-mono text-xs font-semibold tracking-wide text-danger-800 dark:bg-danger-500/20 dark:text-danger-200">{{ $parsed['code'] }}</span>
            @endif
        </div>
    @elseif (filled($label))
        {{-- label already shown --}}
    @else
        <p class="text-xs font-semibold uppercase tracking-wide">Error</p>
    @endif

    @if ($looksLikeJson)
        <pre class="mt-3 max-h-80 overflow-auto whitespace-pre-wrap break-words rounded-md border border-danger-200 bg-white/80 p-3 font-mono text-xs leading-5 text-danger-900 dark:border-danger-500/30 dark:bg-black/20 dark:text-danger-100">{{ $parsed['message'] }}</pre>
    @else
        <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6">{{ $parsed['message'] }}</p>
    @endif

    @if (filled($parsed['details']))
        <details class="mt-3">
            <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide">Response details</summary>
            <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap break-words rounded-md border border-danger-200 bg-white/80 p-3 font-mono text-xs leading-5 text-danger-900 dark:border-danger-500/30 dark:bg-black/20 dark:text-danger-100">{{ $parsed['details'] }}</pre>
        </details>
    @endif

    @if ($total !== null)
        <p class="mt-3 text-xs font-medium uppercase tracking-wide opacity-80">
            {{ $total }} {{ \Illuminate\Support\Str::plural('lead', (int) $total) }}
        </p>
    @endif
</div>
