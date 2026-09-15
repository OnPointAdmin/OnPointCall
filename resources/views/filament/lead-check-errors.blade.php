@php
    /** @var \App\Models\Lead $lead */
    $errors = $lead->checkLastErrors();
@endphp

<div class="space-y-4 text-sm">
    @forelse ($errors as $error)
        @include('filament.partials.check-error', [
            'label' => $error['check'],
            'message' => $error['message'],
        ])
    @empty
        <p class="text-gray-500 dark:text-gray-400">No check errors stored for this lead.</p>
    @endforelse
</div>
