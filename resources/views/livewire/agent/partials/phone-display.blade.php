@php
    $isMobile = false; // JS will enhance; server renders copy-first for all
@endphp

<div class="text-center">
    <p class="text-sm text-slate-500">Phone number</p>

    {{-- Desktop: click copies phone only, no tel: --}}
    <button
        type="button"
        id="phone-copy-btn"
        data-phone="{{ $phone }}"
        class="mt-1 block w-full text-4xl font-bold tracking-wide text-slate-900 hover:text-amber-700 md:text-5xl"
        onclick="copyPhone('{{ $phone }}')"
    >
        {{ $phone }}
    </button>

    {{-- Mobile: tel: only when not manual-dial-only --}}
    @if (! $manualDialOnly)
        <a
            href="tel:{{ $phone }}"
            class="mt-2 inline-block text-sm text-amber-700 md:hidden"
        >
            Tap to dial
        </a>
    @else
        <p class="mt-2 text-xs font-medium text-amber-800 md:hidden">Manual dial only — hand-dial this number</p>
    @endif

    <p class="mt-2 hidden text-xs text-slate-500 md:block" id="phone-copy-hint">Click to copy phone number</p>
</div>

<script>
    function copyPhone(phone) {
        if (window.innerWidth < 768 && !{{ $manualDialOnly ? 'true' : 'false' }}) {
            return;
        }
        navigator.clipboard.writeText(phone).then(() => {
            const hint = document.getElementById('phone-copy-hint');
            if (hint) {
                hint.textContent = 'Copied!';
                setTimeout(() => hint.textContent = 'Click to copy phone number', 2000);
            }
        });
    }
</script>
