@php
    /** @var \App\Models\Lead $lead */
    $matches = $lead->bookingMatches();
@endphp

<div class="space-y-6 text-sm">
    <div class="grid gap-2 sm:grid-cols-3">
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Status</p>
            <p class="font-semibold">{{ $lead->booking_check_status?->label() ?? '—' }}</p>
        </div>
        <div class="sm:col-span-2">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Summary</p>
            <p class="font-semibold">{{ $lead->bookingDetailLabel() ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Checked at</p>
            <p>{{ \App\Support\CompanyTimezone::display($lead->booking_checked_at, format: 'M j, Y g:i A T') ?: '—' }}</p>
        </div>
    </div>

    @if ($lead->booking_check_last_error)
        @include('filament.partials.check-error', ['message' => $lead->booking_check_last_error])
    @endif

    @forelse ($matches as $index => $match)
        <div>
            <h3 class="text-sm font-semibold">Match {{ $index + 1 }}</h3>
            <dl class="mt-2 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
                @foreach ([
                    'salesforce_id' => 'Salesforce ID',
                    'phone' => 'Phone',
                    'tour_date' => 'Tour date',
                    'status' => 'Status',
                    'classification' => 'Classification',
                ] as $key => $label)
                    <div class="grid grid-cols-3 gap-2 px-3 py-2">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="col-span-2 font-mono text-xs">{{ $match[$key] ?? '—' }}</dd>
                    </div>
                @endforeach
                <div class="grid grid-cols-3 gap-2 px-3 py-2">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Matched fields</dt>
                    <dd class="col-span-2 text-xs">
                        @php
                            $matchedFields = $match['matched_fields'] ?? [];
                        @endphp
                        @if (is_array($matchedFields) && $matchedFields !== [])
                            {{ collect($matchedFields)->map(fn (array $field): string => ($field['field'] ?? 'field').(isset($field['booking_field']) ? ' → '.$field['booking_field'] : ''))->implode(', ') }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    @empty
        <p class="text-gray-500 dark:text-gray-400">No booking matches stored.</p>
    @endforelse
</div>
