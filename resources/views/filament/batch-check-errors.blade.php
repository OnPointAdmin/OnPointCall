@php
    /** @var list<array{message: string, total: int}> $errors */
@endphp

<div class="space-y-4 text-sm">
    @forelse ($errors as $error)
        @include('filament.partials.check-error', [
            'message' => $error['message'],
            'total' => $error['total'],
        ])
    @empty
        <p class="text-gray-500 dark:text-gray-400">No error messages were stored for these leads.</p>
    @endforelse
</div>
