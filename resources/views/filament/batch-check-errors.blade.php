@php
    /** @var list<array{message: string, total: int}> $errors */
@endphp

<div class="space-y-4 text-sm">
    @forelse ($errors as $error)
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-danger-800 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300">
            <p class="mt-0 whitespace-pre-wrap">{{ $error['message'] }}</p>
            <p class="mt-1 text-xs font-medium uppercase tracking-wide opacity-80">
                {{ $error['total'] }} {{ \Illuminate\Support\Str::plural('lead', $error['total']) }}
            </p>
        </div>
    @empty
        <p class="text-gray-500 dark:text-gray-400">No error messages were stored for these leads.</p>
    @endforelse
</div>
