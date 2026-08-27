@php
    use App\Enums\Disposition;
    use App\Enums\LeadHistoryType;
    use App\Enums\QualificationStatus;
    use App\Enums\SoftScoreStatus;
    use App\Support\LeadDemographicOptions;
    use App\Support\LeadDisplayFields;

    $isReadOnly = $readOnly || in_array($lead->status->value, ['booked', 'terminal', 'dnc'], true);
    $showSoftScoreRecentModal = $showSoftScoreRecentModal ?? false;
    $canRunSoftScore = $canRunSoftScore ?? false;
    $canRunQualification = $canRunQualification ?? false;
    $softScoreRunning = $softScoreRunning ?? false;
    $qualificationRunning = $qualificationRunning ?? false;
    $readOnlyMessage = $readOnlyMessage ?? null;
    $companyId = (int) $lead->company_id;
    $ageRangeOptions = LeadDemographicOptions::for('age_range', $companyId, $lead->age_range);
    $incomeOptions = LeadDemographicOptions::for('annual_income', $companyId, $lead->annual_income);
    $maritalStatusOptions = LeadDemographicOptions::for('marital_status', $companyId, $lead->marital_status);
    $genderOptions = LeadDemographicOptions::for('gender', $companyId, $lead->gender);
    $homeownerOptions = LeadDemographicOptions::for('home_owner', $companyId, $lead->home_owner);

    $isTnb = $lead->lead_type === 'tnb';
    $filled = fn ($value) => $value !== null && $value !== '';

    $tourFields = collect([
        ['label' => 'Tour location', 'value' => $lead->tour_location],
        ['label' => 'Tour date start', 'value' => $lead->tour_date_start],
        ['label' => 'Tour date', 'value' => $lead->tour_date],
        ['label' => 'Premiums', 'value' => $lead->premiums],
        ['label' => 'Tour result', 'value' => $lead->tour_result],
        ['label' => 'Tour / no show', 'value' => $lead->tour_or_no_show],
        ['label' => 'Booking ID', 'value' => $lead->booking_id],
    ])->filter(fn (array $field) => $filled($field['value']));

    if ($isTnb) {
        foreach ($lead->extra_fields ?? [] as $key => $value) {
            if (! $filled($value)) {
                continue;
            }
            $tourFields->push([
                'label' => ucwords(str_replace('_', ' ', (string) $key)),
                'value' => is_array($value) ? json_encode($value) : (string) $value,
            ]);
        }
    }

    $showTour = $tourFields->isNotEmpty();

    $sectionedKeys = [
        'address', 'address_2', 'zip', 'email', 'age_range', 'annual_income', 'marital_status',
        'gender', 'home_owner', 'original_lead_submit_date', 'booking_id', 'phone_2',
        'tour_location', 'tour_date_start', 'tour_date', 'premiums', 'tour_result', 'tour_or_no_show',
        'external_lead_id', 'first_name', 'last_name', 'phone', 'city', 'state',
    ];

    $extraFields = collect();
    if (! $isTnb) {
        foreach ($lead->extra_fields ?? [] as $key => $value) {
            if (! $filled($value)) {
                continue;
            }
            $extraFields->push([
                'label' => ucwords(str_replace('_', ' ', (string) $key)),
                'value' => is_array($value) ? json_encode($value) : (string) $value,
            ]);
        }
    }
    foreach (LeadDisplayFields::AGENT_FIELD_LABELS as $attribute => $label) {
        if (in_array($attribute, $sectionedKeys, true)) {
            continue;
        }
        $value = $lead->{$attribute};
        if ($value === null || $value === '') {
            continue;
        }
        $extraFields->push(['label' => $label, 'value' => (string) $value]);
    }

    $softScoreDisplay = match ($lead->soft_score_status) {
        SoftScoreStatus::Error => 'Error',
        SoftScoreStatus::Pending => 'Pending',
        SoftScoreStatus::Complete => $lead->soft_score_code ?: '—',
        SoftScoreStatus::Recent => $lead->soft_score_code
            ? $lead->soft_score_code.' (recently checked)'
            : 'Recently checked',
        default => $lead->soft_score_code ?: '—',
    };

    $qualifiedToTourAt = match ($lead->qualification_status) {
        QualificationStatus::Error => 'Error',
        QualificationStatus::Pending => 'Pending',
        QualificationStatus::NotQualified => 'Not qualified',
        QualificationStatus::Qualified => $lead->qualifiedPartnerNames() !== []
            ? implode(', ', $lead->qualifiedPartnerNames())
            : '—',
        default => '—',
    };

    $canPutBackCallback = $canPutBackCallback ?? false;

    $outcomePillClasses = function (?string $dispositionValue): string {
        return match ($dispositionValue) {
            Disposition::Booked->value => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-400',
            Disposition::Callback->value, Disposition::NoAnswer->value, Disposition::LeftVm->value => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-400',
            Disposition::NotInterested->value, Disposition::NotQualified->value, Disposition::WrongNumber->value, Disposition::BadNumber->value, Disposition::Dnc->value => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-400',
            Disposition::Skip->value => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        };
    };
@endphp

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" x-data="{ editMode: false, extraOpen: false, historyOpen: true }">

    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
        <div class="flex items-center gap-2.5">
            <span class="text-xs font-bold uppercase tracking-wide text-blue-600">Active Lead</span>
            <span class="text-xs text-slate-400 dark:text-slate-500">{{ $lead->id }}</span>
        </div>
        <div class="flex flex-shrink-0 items-center gap-2">
            @if ($canPutBackCallback)
                <button
                    type="button"
                    wire:click="putBackCallback"
                    class="whitespace-nowrap rounded-md border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-950"
                >
                    Put Back
                </button>
            @endif
            @unless ($isReadOnly)
                <template x-if="!editMode">
                    <button
                        type="button"
                        x-on:click="editMode = true; $wire.startEdit()"
                        class="whitespace-nowrap rounded-md border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                    >
                        Edit
                    </button>
                </template>
                <template x-if="editMode">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            x-on:click="editMode = false; $wire.cancelEdit()"
                            class="whitespace-nowrap rounded-md border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
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
            @endunless
            @if ($bookingUrl)
                <a
                    href="{{ $bookingUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="whitespace-nowrap rounded-md bg-emerald-600 px-3.5 py-1.5 text-sm font-semibold text-white no-underline hover:bg-emerald-700"
                >
                    Create Booking
                </a>
            @endif
        </div>
    </div>

    @if ($readOnly && $readOnlyMessage)
        <div class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm font-semibold text-amber-900 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-400">
            {{ $readOnlyMessage }}
        </div>
    @elseif ($isReadOnly)
        <div class="border-b border-red-200 bg-red-50 px-5 py-3 text-sm font-semibold text-red-800 dark:border-red-800 dark:bg-red-500/10 dark:text-red-400">
            {{ $lead->status->label() }} — read only
        </div>
    @elseif ($lead->status->value === 'callback' && $lead->callback_at?->isPast())
        <div class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm font-semibold text-amber-900 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-400">
            Callback overdue — {{ \App\Support\CompanyTimezone::display($lead->callback_at) }}
        </div>
    @endif

    @include('livewire.agent.partials.phone-display', [
        'phone' => $lead->phone,
        'name' => $lead->fullName(),
        'manualDialOnly' => $manualDialOnly,
        'phone2' => $lead->phone_2,
        'secondaryName' => trim($lead->first_name_2.' '.$lead->last_name_2) ?: null,
    ])

    <div class="flex flex-col gap-1 px-5 py-5">
        <div>
            <div class="mb-2.5 flex items-center justify-between gap-3">
                <h3 class="m-0 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Qualified to Tour At</h3>
                <div class="m-0 flex shrink-0 select-none flex-col items-end text-right text-xs text-slate-500 dark:text-slate-400" oncopy="return false">
                    <span class="font-bold text-slate-700 dark:text-slate-300">Lead type</span>
                    <span class="text-slate-900 dark:text-slate-100">{{ $lead->leadTypeName() }}</span>
                </div>
            </div>
            <div class="mt-0.5">
                <span wire:loading.remove wire:target="runQualification">
                    @if ($qualificationRunning)
                        <span data-score-check="qualification">
                            @include('livewire.agent.partials.loading-spinner')
                        </span>
                    @else
                        <p class="m-0 text-sm font-bold text-slate-900 dark:text-slate-100">{{ $qualifiedToTourAt }}</p>
                    @endif
                </span>
                <span wire:loading wire:target="runQualification">
                    @include('livewire.agent.partials.loading-spinner', ['label' => ''])
                </span>
            </div>
            @if (! $qualificationRunning && $lead->qualification_status === QualificationStatus::Error && $lead->qualification_last_error)
                <p class="m-0 mt-1 text-xs text-slate-600 dark:text-slate-400">{{ $lead->qualification_last_error }}</p>
            @endif
        </div>

        <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
            <h3 class="m-0 mb-2.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Contact</h3>
            <div class="grid gap-x-4 gap-y-3" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                <template x-if="editMode">
                    <div>
                        <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">First name</p>
                        <input type="text" wire:model="editable.first_name" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                    </div>
                </template>
                <template x-if="editMode">
                    <div>
                        <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Last name</p>
                        <input type="text" wire:model="editable.last_name" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                    </div>
                </template>
                <template x-if="editMode">
                    <div>
                        <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Phone</p>
                        <input type="text" wire:model="editable.phone" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                        @error('editable.phone') <p class="m-0 mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </template>
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Email</p>
                    <template x-if="!editMode">
                        <p class="m-0 mt-0.5 select-none break-words text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->email ?: '—' }}</p>
                    </template>
                    <template x-if="editMode">
                        <input type="email" wire:model="editable.email" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                    </template>
                </div>
                @if ($localTime)
                    <div>
                        <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Lead local time</p>
                        <p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $localTime }}</p>
                    </div>
                @endif
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Last call</p>
                    <p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ \App\Support\CompanyTimezone::display($lead->last_attempt_at) ?? '—' }}</p>
                </div>
            </div>
        </div>

        <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
            <h3 class="m-0 mb-2.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Address</h3>
            <div class="grid gap-x-4 gap-y-3" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Address</p>
                    <template x-if="!editMode">
                        <p class="m-0 mt-0.5 select-none break-words text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ collect([$lead->address, $lead->address_2])->filter()->implode(', ') ?: '—' }}</p>
                    </template>
                    <template x-if="editMode">
                        <div class="mt-0.5 flex gap-1.5">
                            <input type="text" wire:model="editable.address" placeholder="Address" class="w-full flex-[2] rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                            <input type="text" wire:model="editable.address_2" placeholder="Address 2" class="w-full flex-1 rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                        </div>
                    </template>
                </div>
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">City / State / Zip</p>
                    <template x-if="!editMode">
                        <p class="m-0 mt-0.5 select-none break-words text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ collect([$lead->city, $lead->state, $lead->zip])->filter()->implode(', ') ?: '—' }}</p>
                    </template>
                    <template x-if="editMode">
                        <div class="mt-0.5 flex gap-1.5">
                            <input type="text" wire:model="editable.city" placeholder="City" class="w-full flex-[2] rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                            <input type="text" wire:model="editable.state" placeholder="State" class="w-full flex-1 rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                            <input type="text" wire:model="editable.zip" placeholder="Zip" class="w-full flex-1 rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
            <h3 class="m-0 mb-2.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Demographics / profile</h3>
            <div class="grid gap-x-4 gap-y-3" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Age range</p>
                    <template x-if="!editMode"><p class="m-0 mt-0.5 select-none text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->age_range ?: '—' }}</p></template>
                    <template x-if="editMode">
                        <select wire:model="editable.age_range" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                            <option value="">—</option>
                            @foreach ($ageRangeOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                        </select>
                    </template>
                </div>
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Annual income</p>
                    <template x-if="!editMode"><p class="m-0 mt-0.5 select-none text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->annual_income ?: '—' }}</p></template>
                    <template x-if="editMode">
                        <select wire:model="editable.annual_income" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                            <option value="">—</option>
                            @foreach ($incomeOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                        </select>
                    </template>
                </div>
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Marital status</p>
                    <template x-if="!editMode"><p class="m-0 mt-0.5 select-none text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->marital_status ?: '—' }}</p></template>
                    <template x-if="editMode">
                        <select wire:model="editable.marital_status" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                            <option value="">—</option>
                            @foreach ($maritalStatusOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                        </select>
                    </template>
                </div>
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Gender</p>
                    <template x-if="!editMode"><p class="m-0 mt-0.5 select-none text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->gender ?: '—' }}</p></template>
                    <template x-if="editMode">
                        <select wire:model="editable.gender" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                            <option value="">—</option>
                            @foreach ($genderOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                        </select>
                    </template>
                </div>
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Homeowner</p>
                    <template x-if="!editMode"><p class="m-0 mt-0.5 select-none text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->home_owner ?: '—' }}</p></template>
                    <template x-if="editMode">
                        <select wire:model="editable.home_owner" class="mt-0.5 w-full rounded-md border border-blue-300 bg-blue-50 px-2 py-1.5 text-sm dark:bg-blue-500/10">
                            <option value="">—</option>
                            @foreach ($homeownerOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                        </select>
                    </template>
                </div>
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Soft Score</p>
                    <div class="mt-0.5">
                        <span wire:loading.remove wire:target="runSoftScore">
                            @if ($softScoreRunning)
                                <span data-score-check="soft-score">
                                    @include('livewire.agent.partials.loading-spinner')
                                </span>
                            @else
                                <p class="m-0 select-none text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $softScoreDisplay }}</p>
                            @endif
                        </span>
                        <span wire:loading wire:target="runSoftScore">
                            @include('livewire.agent.partials.loading-spinner', ['label' => ''])
                        </span>
                    </div>
                    @if ($lead->soft_score_checked_at && ! $softScoreRunning)
                        <p class="m-0 mt-0.5 text-xs text-slate-500 dark:text-slate-400">Last checked {{ \App\Support\CompanyTimezone::display($lead->soft_score_checked_at, format: 'M j, Y') }}</p>
                    @endif
                </div>
            </div>
            @unless ($isReadOnly)
                @if ($canRunSoftScore)
                    <div class="mt-3">
                        <button
                            type="button"
                            wire:click="runSoftScore"
                            wire:loading.attr="disabled"
                            wire:target="runSoftScore"
                            class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 disabled:opacity-60"
                        >
                            <span wire:loading wire:target="runSoftScore">
                                @include('livewire.agent.partials.loading-spinner', ['label' => null])
                            </span>
                            Run Soft Score
                        </button>
                    </div>
                @endif
            @endunless
        </div>

        @if ($showTour)
            <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                <h3 class="m-0 mb-2.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tour / TNB</h3>
                <div class="grid gap-x-4 gap-y-3" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                    @foreach ($tourFields as $field)
                        <div>
                            <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">{{ $field['label'] }}</p>
                            <p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $field['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
            <h3 class="m-0 mb-2.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Source Information</h3>
            <div class="grid gap-x-4 gap-y-3" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Venue / Event</p>
                    <p class="m-0 mt-0.5 select-none break-words text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ collect([$lead->venue, $lead->event])->filter()->implode(' / ') ?: '—' }}</p>
                </div>
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Source file</p>
                    <p class="m-0 mt-0.5 select-none break-words text-sm text-slate-900 dark:text-slate-100" oncopy="return false">{{ $lead->file_name ?: '—' }}</p>
                </div>
                <div>
                    <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Lead ID</p>
                    <p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $lead->external_lead_id ?: $lead->id }}</p>
                </div>
                @if ($lead->original_lead_submit_date)
                    <div>
                        <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">Original submit date</p>
                        <p class="m-0 mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $lead->original_lead_submit_date }}</p>
                    </div>
                @endif
            </div>
        </div>

        @if ($extraFields->isNotEmpty())
            <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                <button type="button" x-on:click="extraOpen = !extraOpen" class="flex items-center gap-1.5 border-none bg-transparent p-0 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                    <span x-text="extraOpen ? '▾' : '▸'"></span> Extra fields ({{ $extraFields->count() }})
                </button>
                <div x-show="extraOpen" x-cloak class="mt-2.5 grid grid-cols-1 gap-x-4 gap-y-2.5 sm:grid-cols-2 md:grid-cols-3">
                    @foreach ($extraFields as $field)
                        <div>
                            <p class="m-0 text-xs font-bold text-slate-700 dark:text-slate-300">{{ $field['label'] }}</p>
                            <p class="m-0 mt-0.5 select-none text-sm text-slate-700 dark:text-slate-300" oncopy="return false">{{ $field['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($softScoreMessage)
            <p class="mt-3 text-sm text-slate-700 dark:text-slate-300">{{ $softScoreMessage }}</p>
        @endif
        @if ($qualificationMessage)
            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $qualificationMessage }}</p>
        @endif

        @unless ($isReadOnly)
            @if ($canRunQualification)
                <div class="mt-4 flex flex-wrap items-center gap-2.5 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button
                        type="button"
                        wire:click="runQualification"
                        wire:loading.attr="disabled"
                        wire:target="runQualification"
                        class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 disabled:opacity-60"
                    >
                        <span wire:loading wire:target="runQualification">
                            @include('livewire.agent.partials.loading-spinner', ['label' => null])
                        </span>
                        Run Qualification
                    </button>
                </div>
            @endif
        @endunless
    </div>
</div>

@if ($lead->history->isNotEmpty())
    <div class="mt-3.5 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" x-data="{ historyOpen: true }">
        <button
            type="button"
            x-on:click="historyOpen = !historyOpen"
            class="flex w-full items-center justify-between border-b border-slate-100 bg-transparent px-5 py-3.5 dark:border-slate-800"
        >
            <h3 class="m-0 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <span x-text="historyOpen ? '▾' : '▸'"></span> Call History
            </h3>
            <span class="text-xs text-slate-400 dark:text-slate-500">{{ $lead->history->count() }} events</span>
        </button>
        <div x-show="historyOpen" x-cloak class="overflow-x-auto">
            <div class="grid gap-0 bg-slate-50 py-2 dark:bg-slate-950" style="grid-template-columns: 140px 130px 140px 90px minmax(160px, 1fr); min-width: 640px;">
                <span class="pl-5 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Date / Time</span>
                <span class="pl-3 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Outcome</span>
                <span class="pl-3 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Agent</span>
                <span class="pl-3 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Duration</span>
                <span class="pl-3 pr-5 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Note</span>
            </div>
            @foreach ($lead->history as $entry)
                @php
                    $dispositionValue = $entry->payload['disposition'] ?? null;
                    $outcomeLabel = $dispositionValue
                        ? (Disposition::tryFrom($dispositionValue)?->label() ?? $dispositionValue)
                        : $entry->event_type->label();
                    $note = $entry->event_type === LeadHistoryType::FieldEdit
                        ? $entry->detailLabel()
                        : ($entry->noteLabel() ?? '—');
                @endphp
                <div class="grid items-center gap-0 border-t border-slate-100 py-2 dark:border-slate-800" style="grid-template-columns: 140px 130px 140px 90px minmax(160px, 1fr); min-width: 640px;">
                    <span class="whitespace-nowrap pl-5 text-sm text-slate-900 dark:text-slate-100">{{ \App\Support\CompanyTimezone::display($entry->occurred_at) ?? '—' }}</span>
                    <span class="pl-3 text-xs">
                        <span class="inline-block rounded-full px-2.5 py-0.5 font-bold {{ $outcomePillClasses(is_string($dispositionValue) ? $dispositionValue : null) }}">{{ $outcomeLabel }}</span>
                    </span>
                    <span class="pl-3 text-sm text-slate-700 dark:text-slate-300">{{ $entry->actor?->name ?? 'System' }}</span>
                    <span class="pl-3 text-sm text-slate-700 dark:text-slate-300">—</span>
                    <span class="pl-3 pr-5 text-sm text-slate-500 dark:text-slate-400">{{ $note ?: '—' }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if ($showSoftScoreRecentModal && $canRunSoftScore)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="soft-score-recent-title"
    >
        <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-lg dark:border-slate-700 dark:bg-slate-900">
            <h2 id="soft-score-recent-title" class="m-0 text-base font-semibold text-slate-900 dark:text-slate-100">
                Soft Score recently checked
            </h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                Soft Score was checked within the last {{ (int) config('services.soft_score.freshness_days', 15) }} days.
                The existing value on this lead will be kept.
            </p>
            @if ($lead->soft_score_code || $lead->soft_score_checked_at)
                <p class="mt-3 text-sm text-slate-700 dark:text-slate-200">
                    @if ($lead->soft_score_code)
                        Current Soft Score: <span class="font-semibold">{{ $lead->soft_score_code }}</span>
                    @endif
                    @if ($lead->soft_score_checked_at)
                        <span class="block text-xs text-slate-500 dark:text-slate-400">
                            Last checked {{ \App\Support\CompanyTimezone::display($lead->soft_score_checked_at, format: 'M j, Y') }}
                        </span>
                    @endif
                </p>
            @endif
            <div class="mt-5 flex justify-end">
                <button
                    type="button"
                    wire:click="dismissSoftScoreRecentModal"
                    class="rounded-md bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    OK
                </button>
            </div>
        </div>
    </div>
@endif
