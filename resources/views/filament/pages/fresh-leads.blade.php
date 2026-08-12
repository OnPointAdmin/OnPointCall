<x-filament-panels::page>
    <div class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <p class="text-sm text-gray-500 dark:text-gray-400">Matching fresh leads</p>
        <p class="text-3xl font-semibold">{{ number_format($this->holdingCount) }}</p>
    </div>

    {{ $this->content }}
</x-filament-panels::page>
