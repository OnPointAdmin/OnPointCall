@php
    /** @var \App\Models\Lead $lead */
    $leadCompanies = $lead->qualificationCompanies('qualifiedCompaniesLead');
    $bookingCompanies = $lead->qualificationCompanies('qualifiedCompaniesBooking');
    $failed = $lead->qualificationFailedCriteria();
    $request = $lead->qualificationRequest();
    $raw = $lead->qualificationResponse();
    $customerData = is_array($request['customerData'] ?? null) ? $request['customerData'] : [];
@endphp

<div class="space-y-6 text-sm">
    <div class="grid gap-2 sm:grid-cols-2">
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Status</p>
            <p class="font-semibold">{{ $lead->qualification_status?->label() ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Checked at</p>
            <p>{{ \App\Support\CompanyTimezone::display($lead->qualification_checked_at, format: 'M j, Y g:i A T') ?: '—' }}</p>
        </div>
    </div>

    @if ($lead->qualification_last_error)
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-danger-800 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300">
            <p class="text-xs font-semibold uppercase tracking-wide">Error</p>
            <p class="mt-1 whitespace-pre-wrap">{{ $lead->qualification_last_error }}</p>
        </div>
    @endif

    <div>
        <h3 class="text-sm font-semibold">Posted values</h3>
        @if ($request === null)
            <p class="mt-1 text-gray-500 dark:text-gray-400">No request body was stored. Re-run qualification to capture posted values.</p>
        @else
            <dl class="mt-2 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
                <div class="grid grid-cols-3 gap-2 px-3 py-2">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">surveyCompanyId</dt>
                    <dd class="col-span-2 font-mono text-xs">{{ $request['surveyCompanyId'] ?? '—' }}</dd>
                </div>
                <div class="grid grid-cols-3 gap-2 px-3 py-2">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">venueId</dt>
                    <dd class="col-span-2 font-mono text-xs">{{ $request['venueId'] ?? '—' }}</dd>
                </div>
                @forelse ($customerData as $key => $value)
                    <div class="grid grid-cols-3 gap-2 px-3 py-2">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $key }}</dt>
                        <dd class="col-span-2 text-xs">{{ is_scalar($value) ? (string) $value : json_encode($value) }}</dd>
                    </div>
                @empty
                    <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">No customerData fields.</div>
                @endforelse
            </dl>
        @endif
    </div>

    <div>
        <h3 class="text-sm font-semibold">Qualified — Lead</h3>
        @if ($leadCompanies === [])
            <p class="mt-1 text-gray-500 dark:text-gray-400">None</p>
        @else
            <ul class="mt-2 space-y-2">
                @foreach ($leadCompanies as $company)
                    <li class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <p class="font-medium">{{ $company['name'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            @if ($company['vertical']) {{ $company['vertical'] }} @endif
                            @if ($company['priority']) · Priority {{ $company['priority'] }} @endif
                            @if ($company['combination']) · {{ $company['combination'] }} @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div>
        <h3 class="text-sm font-semibold">Qualified — Booking</h3>
        @if ($bookingCompanies === [])
            <p class="mt-1 text-gray-500 dark:text-gray-400">None</p>
        @else
            <ul class="mt-2 space-y-2">
                @foreach ($bookingCompanies as $company)
                    <li class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <p class="font-medium">{{ $company['name'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            @if ($company['vertical']) {{ $company['vertical'] }} @endif
                            @if ($company['priority']) · Priority {{ $company['priority'] }} @endif
                            @if ($company['combination']) · {{ $company['combination'] }} @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div>
        <h3 class="text-sm font-semibold">Why partners failed</h3>
        @if ($failed === [])
            <p class="mt-1 text-gray-500 dark:text-gray-400">No failed-criteria details returned.</p>
        @else
            <ul class="mt-2 space-y-2">
                @foreach ($failed as $row)
                    <li class="rounded-lg border border-warning-300 bg-warning-50 p-3 dark:border-warning-500/40 dark:bg-warning-500/10">
                        <p class="font-medium">{{ $row['name'] }}</p>
                        @if ($row['combination'])
                            <p class="text-xs text-gray-600 dark:text-gray-300">Closest combination: {{ $row['combination'] }}</p>
                        @endif
                        @if ($row['failed'] !== [])
                            <p class="mt-1 text-xs">Failed: {{ implode(', ', $row['failed']) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div>
        <h3 class="text-sm font-semibold">Raw request</h3>
        @if ($request === null)
            <p class="mt-1 text-gray-500 dark:text-gray-400">No request body was stored.</p>
        @else
            <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs dark:border-white/10 dark:bg-white/5">{{ json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        @endif
    </div>

    <div>
        <h3 class="text-sm font-semibold">Raw API response</h3>
        @if ($raw === [])
            <p class="mt-1 text-gray-500 dark:text-gray-400">No response body was stored.</p>
        @else
            <pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs dark:border-white/10 dark:bg-white/5">{{ json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        @endif
    </div>
</div>
