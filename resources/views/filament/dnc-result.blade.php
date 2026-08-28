@php
    /** @var \App\Models\Lead $lead */
    $phones = $lead->dncPhones();
    $hitReason = $lead->dnc_result['hit_reason'] ?? null;
    $ignoredReasons = $lead->dnc_result['ignored_reasons'] ?? [];
    $ignoreNational = (bool) ($lead->dnc_result['ignore_national_dnc'] ?? false);
@endphp

<div class="space-y-6 text-sm">
    <div class="grid gap-2 sm:grid-cols-3">
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Status</p>
            <p class="font-semibold">{{ $lead->dnc_status?->label() ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Hit reason</p>
            <p class="font-semibold">{{ $hitReason ? strtoupper($hitReason) : '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Checked at</p>
            <p>{{ \App\Support\CompanyTimezone::display($lead->dnc_checked_at, format: 'M j, Y g:i A T') ?: '—' }}</p>
        </div>
    </div>

    @if ($ignoreNational || $ignoredReasons !== [])
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="text-xs font-semibold uppercase tracking-wide">Registry DNC ignored</p>
            <p class="mt-1">
                This batch has TCPA / prior express consent. National and state DNC
                {{ $ignoredReasons !== [] ? 'were recorded but did not block the lead' : 'would not block the lead' }}.
                Litigator and internal DNC still apply.
            </p>
        </div>
    @endif

    @if ($lead->dnc_last_error)
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-danger-800 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300">
            <p class="text-xs font-semibold uppercase tracking-wide">Error</p>
            <p class="mt-1 whitespace-pre-wrap">{{ $lead->dnc_last_error }}</p>
        </div>
    @endif

    @forelse ($phones as $field => $phone)
        <div>
            <h3 class="text-sm font-semibold">{{ $field === 'phone_2' ? 'Phone 2' : 'Phone' }}</h3>
            <dl class="mt-2 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
                @foreach ([
                    'phone' => 'Number',
                    'result_code' => 'Result code',
                    'reason' => 'Reason',
                    'suppress' => 'Suppress',
                    'flags' => 'Flags',
                    'region' => 'Region',
                    'locale' => 'Locale',
                    'carrier_info' => 'Carrier',
                    'line_type' => 'Line type',
                ] as $key => $label)
                    <div class="grid grid-cols-3 gap-2 px-3 py-2">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="col-span-2 font-mono text-xs">
                            @if ($key === 'flags')
                                {{ isset($phone['flags']) && is_array($phone['flags']) && $phone['flags'] !== [] ? implode(', ', $phone['flags']) : '—' }}
                            @else
                                {{ $phone[$key] ?? '—' }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @empty
        <p class="text-gray-500 dark:text-gray-400">No scrub details stored.</p>
    @endforelse
</div>
