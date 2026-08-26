{{-- Hero phone block: the one thing an agent needs before dialing. --}}
@php
    use App\Support\PhoneNormalizer;

    $displayPhone = PhoneNormalizer::formatForDisplay($phone) ?? $phone;
    $displayPhone2 = isset($phone2) && $phone2 ? (PhoneNormalizer::formatForDisplay($phone2) ?? $phone2) : null;
@endphp
<div class="border-b border-slate-100 px-5 py-8 text-center dark:border-slate-800">
    <p class="m-0 mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Phone number</p>

    <button
        type="button"
        id="phone-copy-btn"
        data-phone="{{ $phone }}"
        data-manual-dial-only="{{ $manualDialOnly ? '1' : '0' }}"
        class="block w-full cursor-pointer border-none bg-transparent p-0 font-extrabold leading-tight tracking-tight text-slate-900 hover:text-blue-700 dark:text-slate-100 dark:hover:text-blue-400"
        style="font-size: clamp(34px, 8vw, 56px);"
        aria-label="Copy phone number {{ $displayPhone }}"
        x-on:click="window.opcCopyPhone($el.dataset.phone, $el.dataset.manualDialOnly === '1', document.getElementById('phone-copy-hint'))"
    >
        {{ $displayPhone }}
    </button>
    <p
        class="m-0 mt-2 hidden text-sm text-slate-400 dark:text-slate-500 md:block"
        id="phone-copy-hint"
        aria-live="polite"
    >Click to copy phone number</p>

    @if (! $manualDialOnly)
        <a
            href="tel:{{ $phone }}"
            class="mt-2 inline-block text-sm text-blue-700 md:hidden dark:text-blue-400"
        >
            Tap to dial
        </a>
    @else
        <p class="m-0 mt-2 text-xs font-medium text-amber-800 md:hidden dark:text-amber-400">Manual dial only — hand-dial this number</p>
    @endif

    @if ($name ?? null)
        <p class="m-0 mt-2.5 select-none text-xl font-semibold text-slate-900 dark:text-slate-100" oncopy="return false">{{ $name }}</p>
    @endif

    @if ($manualDialOnly)
        <p class="m-0 mt-1.5 inline-block rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800 dark:bg-amber-500/15 dark:text-amber-400">
            Manual dial only — hand-dial this number
        </p>
    @endif

    @if ($displayPhone2 || ($secondaryName ?? null))
        <p class="m-0 mt-2.5 text-sm text-slate-500 dark:text-slate-400">
            Secondary contact:
            @if ($displayPhone2)
                <span class="select-none font-semibold text-slate-700 dark:text-slate-300" oncopy="return false">{{ $displayPhone2 }}</span>
            @endif
            @if ($displayPhone2 && ($secondaryName ?? null))
                <span class="text-slate-400"> · </span>
            @endif
            @if ($secondaryName ?? null)
                <span class="select-none font-semibold text-slate-700 dark:text-slate-300" oncopy="return false">{{ $secondaryName }}</span>
            @endif
        </p>
    @endif
</div>
