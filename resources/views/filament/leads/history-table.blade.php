@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\LeadHistory> $entries */
@endphp

<div class="overflow-x-auto">
    <table class="w-full border-collapse text-sm">
        <thead>
            <tr>
                <th class="border-b border-gray-200 px-3 py-2 text-left font-semibold dark:border-white/10">When</th>
                <th class="border-b border-gray-200 px-3 py-2 text-left font-semibold dark:border-white/10">Event</th>
                <th class="border-b border-gray-200 px-3 py-2 text-left font-semibold dark:border-white/10">Actor</th>
                <th class="border-b border-gray-200 px-3 py-2 text-left font-semibold dark:border-white/10">Details</th>
                <th class="border-b border-gray-200 px-3 py-2 text-left font-semibold dark:border-white/10">Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td class="border-b border-gray-100 px-3 py-2 align-top dark:border-white/10">
                        {{ \App\Support\CompanyTimezone::display($entry->occurred_at, $entry->company_id, 'M j, Y g:i A T') ?: '—' }}
                    </td>
                    <td class="border-b border-gray-100 px-3 py-2 align-top dark:border-white/10">
                        {{ $entry->event_type->label() }}
                    </td>
                    <td class="border-b border-gray-100 px-3 py-2 align-top dark:border-white/10">
                        {{ $entry->actor?->name ?? 'System' }}
                    </td>
                    <td class="border-b border-gray-100 px-3 py-2 align-top dark:border-white/10">
                        {{ $entry->detailLabel() }}
                    </td>
                    <td class="border-b border-gray-100 px-3 py-2 align-top dark:border-white/10">
                        {{ $entry->noteLabel() ?? '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-3 py-4 text-gray-500 dark:text-gray-400">No history yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
