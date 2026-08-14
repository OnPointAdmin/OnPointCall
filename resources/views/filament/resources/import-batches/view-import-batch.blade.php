<x-filament-panels::page>
    @if ($this->isProcessing())
        <div
            wire:poll.5s="refreshRecord"
            class="mb-4 rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400"
        >
            Import in progress — this page refreshes automatically.
        </div>
    @endif

    @php
        $record = $this->getRecord();
        $health = $record->healthStatus();
    @endphp

    @if ($health === 'error')
        <div class="mb-4 rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 inline-block h-3 w-3 shrink-0 rounded-full bg-danger-500"></span>
                <div>
                    <p class="font-semibold">This batch has errors</p>
                    @if (filled($record->error_message))
                        <p class="mt-1">{{ $record->error_message }}</p>
                    @else
                        <p class="mt-1">
                            Soft Score errors: {{ (int) $record->soft_score_error }}
                            · RND errors: {{ (int) $record->rnd_error }}
                            · Qualification errors: {{ (int) $record->qualification_error }}
                        </p>
                        <p class="mt-1 text-danger-600/80 dark:text-danger-300/80">
                            See the Error column in the leads list below for details.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @elseif ($health === 'pending')
        <div class="mb-4 rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
            <div class="flex items-center gap-3">
                <span class="inline-block h-3 w-3 shrink-0 rounded-full bg-warning-400"></span>
                <p class="font-semibold">Checks still in progress</p>
            </div>
        </div>
    @endif

    {{ $this->content }}
</x-filament-panels::page>
