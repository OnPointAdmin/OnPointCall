@php
    $isReadOnly = $readOnly || in_array($lead->status->value, ['booked', 'terminal', 'dnc']);
    $ageRangeOptions = ['Under 25', '25 - 27', '28 - 29', '30 - 39', '40 - 49', '50 - 59', '60+'];
    $incomeOptions = ['Below $25k', '$25k - $49k', '$50k - $59k', '$60k - $74k', '$75k - $99k', '$100K +'];
    $maritalStatusOptions = ['Married', 'Single', 'Engaged', 'Cohabitating'];
    $genderOptions = ['Male', 'Female', 'Other'];
    $homeownerOptions = ['Homeowner (0–3 years)', 'Homeowner (3+ years)', 'Currently Rent/Lease'];
    $hasDemographics = collect([$lead->age_range, $lead->income, $lead->marital_status, $lead->gender, $lead->homeowner])->filter()->isNotEmpty();
    $hasTour = collect([$lead->tour_location, $lead->tour_date, $lead->premiums, $lead->tour_result])->filter()->isNotEmpty();
    $extraFields = $lead->extraFields ?? collect();
@endphp

<div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden" x-data="{ editMode: false, extraOpen: false }">

    {{-- Card header --}}
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 px-5 py-3">
        <div class="flex items-center gap-2.5">
            <span class="text-xs font-bold uppercase tracking-wide text-blue-600">Active Lead</span>
            <span class="text-xs text-slate-400 dark:text-slate-500">{{ $lead->id }} &middot; Attempt {{ $lead->attempts }}</span>
        </div>
        <div class="flex flex-shrink-0 items-center gap-2">
            <template x-if="!editMode">
                <button
                    type="button"
                    x-on:click="editMode = true; $wire.startEdit()"
                    class="whitespace-nowrap rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-1.5 text-sm font-semibold text-slate-700 dark:text-slate-300"
                >
                    Edit
                </button>
            </template>
            <template x-if="editMode">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        x-on:click="editMode = false; $wire.cancelEdit()"
                        class="whitespace-nowrap rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-1.5 text-sm font-semibold text-slate-700 dark:text-slate-300"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        x-on:click="editMode = false; $wire.saveLeadEdits()"
                        class="whitespace-nowrap rounded-md border-none bg-blue-600 px-3.5 py-1.5 text-sm font-bold text-white hover:bg-blue-700"
                    >
                        Save
                    </button>
                </div>
            </template>
            @if (! $isReadOnly)
                <a
                    href="{{ $bookingUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="whitespace-nowrap rounded-md bg-emerald-600 px-3.5 py-1.5 text-sm font-semibold text-white no-underline hover:bg-emerald-700"
                >
                    Open Booking Form
                </a>
            @endif
        </div>
    </div>

    @if ($isReadOnly)
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 bg-blue-50 dark:bg-blue-500/10 px-5 py-3 text-sm font-semibold text-blue-700 dark:text-blue-400">
            <span>Call dispositioned</span>
            <button type="button" wire:click="getNextLead" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">Get Next Lead</button>
        </div>
    @endif

    @include('livewire.agent.partials.phone-display', ['lead' => $lead, 'manualDialOnly' => $manualDialOnly])

    {{-- Field sections --}}
    <div class="flex flex-col gap-1 px-5 py-5">

        @if ($lead->email)
            <div>
                <h3 class="m-0 mb-2.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Contact</h3>
                <div class="grid gap-x-4 gap-y-3" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                    <div>
                        <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Email</p>
                        <template x-if="!editMode">
                            <p class="m-0 mt-0.5 select-none overflow-wrap-anywhere text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->email }}</p>
                        </template>
                        <template x-if="editMode">
                            <input type="email" wire:model="editable.email" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 dark:bg-blue-500/10 px-2 py-1.5 text-sm">
                        </template>
                    </div>
                    <div>
                        <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Lead local time</p>
                        <p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $localTime }}</p>
                    </div>
                    <div>
                        <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Last call</p>
                        <p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $lead->last_call_at?->format('M j, g:i A') ?? '—' }}</p>
                    </div>
                </div>
            </div>
        @endif

        <div class="{{ $lead->email ? 'mt-4 ' : '' }}border-t border-slate-100 dark:border-slate-800 pt-4">
            <h3 class="m-0 mb-2.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Address</h3>
            <div class="grid gap-x-4 gap-y-3" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                <div>
                    <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">City / State / Zip</p>
                    <template x-if="!editMode">
                        <p class="m-0 mt-0.5 select-none overflow-wrap-anywhere text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->city }}, {{ $lead->state }} {{ $lead->zip }}</p>
                    </template>
                    <template x-if="editMode">
                        <div class="mt-0.5 flex gap-1.5">
                            <input type="text" wire:model="editable.city" placeholder="City" class="w-full flex-[2] rounded-md border border-blue-300 bg-blue-50 dark:bg-blue-500/10 px-2 py-1.5 text-sm">
                            <input type="text" wire:model="editable.state" placeholder="State" class="w-full flex-1 rounded-md border border-blue-300 bg-blue-50 dark:bg-blue-500/10 px-2 py-1.5 text-sm">
                            <input type="text" wire:model="editable.zip" placeholder="Zip" class="w-full flex-1 rounded-md border border-blue-300 bg-blue-50 dark:bg-blue-500/10 px-2 py-1.5 text-sm">
                        </div>
                    </template>
                </div>
                <div>
                    <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Address</p>
                    <template x-if="!editMode">
                        <p class="m-0 mt-0.5 select-none overflow-wrap-anywhere text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->address }}, {{ $lead->address2 }}</p>
                    </template>
                    <template x-if="editMode">
                        <div class="mt-0.5 flex gap-1.5">
                            <input type="text" wire:model="editable.address" placeholder="Address" class="w-full flex-[2] rounded-md border border-blue-300 bg-blue-50 dark:bg-blue-500/10 px-2 py-1.5 text-sm">
                            <input type="text" wire:model="editable.address2" placeholder="Address 2" class="w-full flex-1 rounded-md border border-blue-300 bg-blue-50 dark:bg-blue-500/10 px-2 py-1.5 text-sm">
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="mt-4 border-t border-slate-100 dark:border-slate-800 pt-4">
            <h3 class="m-0 mb-2.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Opportunity context</h3>
            <div class="grid gap-x-4 gap-y-3" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                <div><p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Venue / Event</p><p class="m-0 mt-0.5 select-none overflow-wrap-anywhere text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->venue }} / {{ $lead->event }}</p></div>
                <div><p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Partners</p><p class="m-0 mt-0.5 select-none overflow-wrap-anywhere text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->partners }}</p></div>
                <div><p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Source file</p><p class="m-0 mt-0.5 select-none overflow-wrap-anywhere text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->source_file }}</p></div>
                <div><p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Lead ID</p><p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $lead->id }}</p></div>
            </div>
        </div>

        @if ($hasDemographics)
            <div class="mt-4 border-t border-slate-100 dark:border-slate-800 pt-4">
                <h3 class="m-0 mb-2.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Demographics / profile</h3>
                <div class="grid gap-x-4 gap-y-3" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                    <div>
                        <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Age range</p>
                        <template x-if="!editMode"><p class="m-0 mt-0.5 select-none text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->age_range }}</p></template>
                        <template x-if="editMode">
                            <select wire:model="editable.age_range" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 dark:bg-blue-500/10 px-2 py-1.5 text-sm">
                                @foreach ($ageRangeOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                            </select>
                        </template>
                    </div>
                    <div>
                        <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Annual income</p>
                        <template x-if="!editMode"><p class="m-0 mt-0.5 select-none text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->income }}</p></template>
                        <template x-if="editMode">
                            <select wire:model="editable.income" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 dark:bg-blue-500/10 px-2 py-1.5 text-sm">
                                @foreach ($incomeOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                            </select>
                        </template>
                    </div>
                    <div>
                        <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Marital status</p>
                        <template x-if="!editMode"><p class="m-0 mt-0.5 select-none text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->marital_status }}</p></template>
                        <template x-if="editMode">
                            <select wire:model="editable.marital_status" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 dark:bg-blue-500/10 px-2 py-1.5 text-sm">
                                @foreach ($maritalStatusOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                            </select>
                        </template>
                    </div>
                    <div>
                        <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Gender</p>
                        <template x-if="!editMode"><p class="m-0 mt-0.5 select-none text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->gender }}</p></template>
                        <template x-if="editMode">
                            <select wire:model="editable.gender" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 dark:bg-blue-500/10 px-2 py-1.5 text-sm">
                                @foreach ($genderOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                            </select>
                        </template>
                    </div>
                    <div>
                        <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Homeowner</p>
                        <template x-if="!editMode"><p class="m-0 mt-0.5 select-none text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->homeowner }}</p></template>
                        <template x-if="editMode">
                            <select wire:model="editable.homeowner" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 dark:bg-blue-500/10 px-2 py-1.5 text-sm">
                                @foreach ($homeownerOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                            </select>
                        </template>
                    </div>
                    <div>
                        <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Soft Score</p>
                        <p class="m-0 mt-0.5 text-sm font-bold text-slate-900 dark:text-slate-100">{{ $lead->soft_score_display ?? '—' }}</p>
                    </div>
                </div>
            </div>
        @else
            <div class="mt-4 border-t border-slate-100 dark:border-slate-800 pt-4">
                <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Soft Score</p>
                <p class="m-0 mt-0.5 text-sm font-bold text-slate-900 dark:text-slate-100">{{ $lead->soft_score_display ?? '—' }}</p>
            </div>
        @endif

        @if ($hasTour)
            <div class="mt-4 border-t border-slate-100 dark:border-slate-800 pt-4">
                <h3 class="m-0 mb-2.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tour / TNB</h3>
                <div class="grid gap-x-4 gap-y-3" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                    <div><p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Tour location</p><p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $lead->tour_location }}</p></div>
                    <div><p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Tour date</p><p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $lead->tour_date }}</p></div>
                    <div><p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Premiums</p><p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $lead->premiums }}</p></div>
                    <div><p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Tour result</p><p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $lead->tour_result }}</p></div>
                    <div><p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Tour / no show</p><p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $lead->tour_no_show }}</p></div>
                    <div><p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Original submit date</p><p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $lead->original_submit_date }}</p></div>
                    <div><p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">Booking ID</p><p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $lead->booking_id }}</p></div>
                </div>
            </div>
        @endif

        @if ($extraFields->isNotEmpty())
            <div class="mt-4 border-t border-slate-100 dark:border-slate-800 pt-4">
                <button type="button" x-on:click="extraOpen = !extraOpen" class="flex items-center gap-1.5 border-none bg-transparent p-0 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                    <span x-text="extraOpen ? '▾' : '▸'"></span> Extra fields ({{ $extraFields->count() }})
                </button>
                <div x-show="extraOpen" x-cloak class="mt-2.5 grid grid-cols-3 gap-x-4 gap-y-2.5">
                    @foreach ($extraFields as $field)
                        <div>
                            <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $field['label'] }}</p>
                            <p class="m-0 mt-0.5 select-none text-sm text-slate-700 dark:text-slate-300" oncopy="return false">{{ $field['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (! $isReadOnly)
            <div class="mt-4 flex flex-wrap items-center gap-2.5 border-t border-slate-100 dark:border-slate-800 pt-4">
                <button type="button" wire:click="runSoftScore" class="rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2 text-sm font-medium text-slate-700 dark:text-slate-300">Run Soft Score</button>
            </div>
        @endif
    </div>
</div>

{{-- Call History --}}
<div class="mt-3.5 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 px-5 py-3.5">
        <h3 class="m-0 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Call History</h3>
        <span class="text-xs text-slate-400 dark:text-slate-500">{{ $lead->history->count() }} calls on this lead</span>
    </div>
    <div class="overflow-x-auto">
        <div class="grid gap-0 bg-slate-50 dark:bg-slate-950 py-2" style="grid-template-columns: 140px 130px 140px 90px minmax(160px, 1fr); min-width: 640px;">
            <span class="pl-5 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Date / Time</span>
            <span class="pl-3 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Outcome</span>
            <span class="pl-3 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Agent</span>
            <span class="pl-3 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Duration</span>
            <span class="pl-3 pr-5 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Note</span>
        </div>
        @foreach ($lead->history as $call)
            <div class="grid items-center gap-0 border-t border-slate-100 dark:border-slate-800 py-2" style="grid-template-columns: 140px 130px 140px 90px minmax(160px, 1fr); min-width: 640px;">
                <span class="whitespace-nowrap pl-5 text-sm text-slate-900 dark:text-slate-100">{{ $call->created_at->format('M j, g:i A') }}</span>
                <span class="pl-3 text-xs">
                    <span class="inline-block rounded-full px-2.5 py-0.5 font-bold {{ $call->outcomeBadgeClasses() }}">{{ $call->outcomeLabel() }}</span>
                </span>
                <span class="pl-3 text-sm text-slate-700 dark:text-slate-300">{{ $call->agent?->name ?? 'System' }}</span>
                <span class="pl-3 text-sm text-slate-700 dark:text-slate-300">{{ $call->duration ?? '—' }}</span>
                <span class="pl-3 pr-5 text-sm text-slate-500 dark:text-slate-400">{{ $call->note }}</span>
            </div>
        @endforeach
    </div>
</div>
