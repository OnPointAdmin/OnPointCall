@php
    /** @var \App\Models\Lead $lead */
    $errors = $lead->checkLastErrors();
@endphp

<div class="space-y-4 text-sm">
    @forelse ($errors as $error)
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-danger-800 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300">
            <p class="text-xs font-semibold uppercase tracking-wide">{{ $error['check'] }}</p>
            <p class="mt-1 whitespace-pre-wrap">{{ $error['message'] }}</p>
        </div>
    @empty
        <p class="text-gray-500 dark:text-gray-400">No check errors stored for this lead.</p>
    @endforelse
</div>
