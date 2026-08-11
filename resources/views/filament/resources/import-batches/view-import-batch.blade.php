<x-filament-panels::page
    @if ($this->isProcessing())
        wire:poll.5s="refreshRecord"
    @endif
>
    @if ($this->isProcessing())
        <div class="mb-4 rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
            Import in progress — this page refreshes automatically.
        </div>
    @endif

    {{ $this->content }}
</x-filament-panels::page>
