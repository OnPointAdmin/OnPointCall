@php
    $secondaryTabs = ['scoreboard' => 'Scoreboard', 'leaderboard' => 'Leaderboard', 'callbacks' => 'Callbacks', 'lookup' => 'Lookup'];
    $softScoreRunning = $softScoreRunning ?? false;
    $qualificationRunning = $qualificationRunning ?? false;
    $leadIsWorkable = $lead && ! $leadReadOnly && ! in_array($lead->status->value, ['booked', 'terminal', 'dnc'], true);
    $canPutBackCallback = $lead
        && $lead->status->value === 'callback'
        && $lead->callback_owner_id === auth('agent')->id();
    $contactDispositions = ($activeDispositionsByGroup['contact'] ?? collect());
    $callbackDisposition = $contactDispositions->first(fn ($d) => $d->outcome->value === 'callback');
    $otherContactDispositions = $contactDispositions->filter(fn ($d) => $d->outcome->value !== 'callback');
@endphp

<div
    class="grid grid-cols-1 items-start gap-5 md:grid-cols-[1fr_320px]"
    @if ($softScoreRunning || $qualificationRunning)
        wire:poll.2s
    @else
        wire:poll.10s
    @endif
>

    {{-- ACTIVE LEAD WORKSPACE (dominant) --}}
    <div>
        @if (! $lead)
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="m-0 text-lg font-semibold text-slate-900 dark:text-slate-100">No active lead</h2>
                    <button
                        type="button"
                        wire:click="getNextLead"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Get Next Lead
                    </button>
                </div>

                @if ($emptyMessage)
                    <p class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300">
                        {{ $emptyMessage }}
                    </p>
                @endif
            </div>
        @else
            @include('livewire.agent.partials.lead-panel', [
                'lead' => $lead,
                'bookingUrl' => $this->bookingUrl,
                'manualDialOnly' => $this->manualDialOnly,
                'localTime' => $this->localTime,
                'readOnly' => $leadReadOnly,
                'readOnlyMessage' => $leadReadOnlyMessage,
                'canRunSoftScore' => $canRunSoftScore,
                'canRunQualification' => $canRunQualification,
                'softScoreRunning' => $softScoreRunning,
                'qualificationRunning' => $qualificationRunning,
                'softScoreMessage' => $softScoreMessage,
                'qualificationMessage' => $qualificationMessage,
                'showSoftScoreRecentModal' => $showSoftScoreRecentModal,
                'canPutBackCallback' => $canPutBackCallback,
                'dispositionDefinitions' => $dispositionDefinitions,
            ])
        @endif
    </div>

    {{-- RIGHT COLUMN: disposition (primary action) above secondary panels --}}
    <div class="sticky top-4 flex flex-col gap-3.5 overflow-visible" x-data="{ tab: @js($defaultSecondaryTab) }">

        @if ($leadIsWorkable)
            <div
                class="rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-[0_2px_12px_rgba(15,23,42,0.06)] dark:border-slate-800 dark:bg-slate-900"
                x-data="{
                    selected: null,
                    modalOpen: false,
                    pendingKey: null,
                    pendingLabel: '',
                    requiresReasonSlugs: @js($requiresReasonSlugs),
                    pendingRequiresReason() {
                        return this.requiresReasonSlugs.includes(this.pendingKey);
                    },
                    openDisposition(slug, label, needsReason = false) {
                        this.pendingKey = slug;
                        this.pendingLabel = label;
                        this.modalOpen = true;
                        if (needsReason) {
                            $wire.set('dispositionReasonId', null);
                        }
                    },
                }"
            >
                <h4 class="m-0 mb-3 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Disposition</h4>

                <div class="flex flex-col gap-2">
                    @foreach (($activeDispositionsByGroup['primary'] ?? collect()) as $disposition)
                        <button
                            type="button"
                            x-on:click="selected = '{{ $disposition->slug }}'; openDisposition('{{ $disposition->slug }}', '{{ $disposition->label }}', {{ $disposition->requires_reason ? 'true' : 'false' }})"
                            class="w-full rounded-lg border py-3.5 text-[15px] font-bold {{ $disposition->color->buttonClasses() }}"
                        >
                            {{ $disposition->label }}
                        </button>
                    @endforeach

                    @if ($contactDispositions->isNotEmpty())
                        <div class="grid grid-cols-2 gap-2">
                            @if ($callbackDisposition)
                                <button
                                    type="button"
                                    x-on:click="selected = '{{ $callbackDisposition->slug }}'"
                                    class="rounded-lg border py-2.5 text-sm font-semibold {{ $callbackDisposition->color->buttonClasses() }}"
                                >
                                    {{ $callbackDisposition->label }}
                                </button>
                            @endif
                            @foreach ($otherContactDispositions as $disposition)
                                <button
                                    type="button"
                                    x-on:click="selected = '{{ $disposition->slug }}'; openDisposition('{{ $disposition->slug }}', '{{ $disposition->label }}', {{ $disposition->requires_reason ? 'true' : 'false' }})"
                                    class="rounded-lg border py-2.5 text-sm font-semibold {{ $disposition->color->buttonClasses() }}"
                                >
                                    {{ $disposition->label }}
                                </button>
                            @endforeach
                        </div>

                        @if ($callbackDisposition)
                            <div x-show="selected === '{{ $callbackDisposition->slug }}'" x-cloak class="flex flex-col gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 dark:border-amber-800 dark:bg-amber-500/10">
                                <label class="text-xs font-semibold text-amber-800 dark:text-amber-400">Callback date/time</label>
                                <div
                                    wire:ignore
                                    class="grid grid-cols-1 gap-1.5"
                                    x-data="{
                                        date: @js(str_contains($callbackAt, 'T') ? strstr($callbackAt, 'T', true) : ''),
                                        time: @js(str_contains($callbackAt, 'T') ? substr(strstr($callbackAt, 'T'), 1) : ''),
                                        sync() {
                                            $wire.set('callbackAt', this.date && this.time ? (this.date + 'T' + this.time) : '');
                                        },
                                        openPicker(event) {
                                            const input = event.currentTarget;
                                            if (typeof input.showPicker === 'function') {
                                                try { input.showPicker(); } catch (e) {}
                                            }
                                        },
                                    }"
                                >
                                    <input
                                        type="date"
                                        x-model="date"
                                        x-on:change="sync()"
                                        x-on:click="openPicker($event)"
                                        class="w-full min-h-10 cursor-pointer rounded-md border border-amber-300 bg-white px-3 py-1.5 text-sm dark:bg-slate-900"
                                    >
                                    <input
                                        type="time"
                                        x-model="time"
                                        x-on:change="sync()"
                                        x-on:click="openPicker($event)"
                                        class="w-full min-h-10 cursor-pointer rounded-md border border-amber-300 bg-white px-3 py-1.5 text-sm dark:bg-slate-900"
                                    >
                                </div>
                                <p class="m-0 text-[11px] text-amber-800/80 dark:text-amber-400/80">Times use {{ $agentTimezone }}</p>
                                @error('callbackAt') <p class="m-0 text-xs text-red-600">{{ $message }}</p> @enderror
                                <button
                                    type="button"
                                    x-on:click="openDisposition('{{ $callbackDisposition->slug }}', '{{ $callbackDisposition->label }}')"
                                    class="rounded-md bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-700"
                                >
                                    Continue
                                </button>
                            </div>
                        @endif
                    @endif

                    @if (($activeDispositionsByGroup['negative'] ?? collect())->isNotEmpty() || ($activeDispositionsByGroup['compliance'] ?? collect())->isNotEmpty())
                        <div class="my-1 h-px bg-slate-200 dark:bg-slate-700"></div>
                    @endif

                    @if (($activeDispositionsByGroup['negative'] ?? collect())->isNotEmpty())
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (($activeDispositionsByGroup['negative'] ?? collect()) as $disposition)
                                <button
                                    type="button"
                                    x-on:click="selected = '{{ $disposition->slug }}'; openDisposition('{{ $disposition->slug }}', '{{ $disposition->label }}', {{ $disposition->requires_reason ? 'true' : 'false' }})"
                                    class="rounded-lg border py-2.5 text-sm font-semibold {{ $disposition->color->buttonClasses() }}"
                                >
                                    {{ $disposition->label }}
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @foreach (($activeDispositionsByGroup['compliance'] ?? collect()) as $disposition)
                        <button
                            type="button"
                            x-on:click="selected = '{{ $disposition->slug }}'; openDisposition('{{ $disposition->slug }}', '{{ $disposition->label }}', {{ $disposition->requires_reason ? 'true' : 'false' }})"
                            class="w-full rounded-lg border py-2.5 text-sm font-semibold {{ $disposition->color->buttonClasses() }}"
                        >
                            {{ $disposition->label }}
                        </button>
                    @endforeach

                    @if (($activeDispositionsByGroup['utility'] ?? collect())->isNotEmpty() || $canPutBackCallback)
                        <div class="mt-1 border-t border-dashed border-slate-200 pt-3 dark:border-slate-800">
                            @if ($canPutBackCallback)
                                <button
                                    type="button"
                                    wire:click="putBackCallback"
                                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-950"
                                >
                                    Put Back
                                </button>
                                <p class="m-0 mt-1.5 text-[11px] leading-snug text-slate-400 dark:text-slate-500">Keeps this callback. Open it again when you are ready to call.</p>
                            @else
                                @foreach (($activeDispositionsByGroup['utility'] ?? collect()) as $disposition)
                                    <button
                                        type="button"
                                        x-on:click="selected = '{{ $disposition->slug }}'; openDisposition('{{ $disposition->slug }}', '{{ $disposition->label }}', true); $wire.set('dispositionReasonId', null)"
                                        class="w-full rounded-lg border border-dashed py-2 text-sm font-medium {{ $disposition->color->buttonClasses() }}"
                                    >
                                        {{ $disposition->label }}
                                    </button>
                                @endforeach
                            @endif
                        </div>
                    @endif
                </div>

                <div x-show="modalOpen" x-cloak style="position: fixed; inset: 0; z-index: 50;" class="flex items-center justify-center bg-slate-900/45 p-5">
                    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900" x-on:click.outside="modalOpen = false">
                        <h3 class="m-0 text-base font-bold text-slate-900 dark:text-slate-100">Add a note</h3>
                        <p class="m-0 mt-1 text-sm text-slate-500 dark:text-slate-400">Dispositioning as <strong class="text-slate-700 dark:text-slate-300" x-text="pendingLabel"></strong>. Optional &mdash; add context for the next call.</p>
                        <div x-show="pendingRequiresReason()" x-cloak class="mt-3">
                            <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Reason (required)</label>
                            <select
                                wire:model="dispositionReasonId"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                            >
                                <option value="">Select a reason</option>
                                @foreach ($dispositionReasonsBySlug as $slug => $reasons)
                                    @foreach ($reasons as $reason)
                                        <option value="{{ $reason->id }}" x-bind:hidden="pendingKey !== '{{ $slug }}'">{{ $reason->label }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            @error('dispositionReasonId') <p class="m-0 mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <textarea
                            wire:model="dispositionNote"
                            placeholder="e.g. Asked to call back after 5pm, interested in pricing"
                            rows="4"
                            class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                        ></textarea>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" x-on:click="modalOpen = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-950">Cancel</button>
                            <button type="button" x-on:click="$wire.applyDisposition(pendingKey)" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">Save &amp; Disposition</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex border-b border-slate-100 dark:border-slate-800">
                @foreach ($secondaryTabs as $key => $label)
                    <button
                        type="button"
                        x-on:click="tab = '{{ $key }}'"
                        x-bind:class="tab === '{{ $key }}' ? 'text-blue-600 border-blue-600' : 'text-slate-400 dark:text-slate-500 border-transparent'"
                        class="flex-1 border-b-2 bg-transparent px-1.5 py-2.5 text-xs font-semibold"
                    >
                        {{ $label }}
                        @if ($key === 'callbacks' && $overdueCallbackCount > 0)
                            <span class="ml-1 inline-flex min-w-4 justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold text-white">{{ $overdueCallbackCount }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="p-3.5">
                <div x-show="tab === 'scoreboard'">
                    <p class="m-0 mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                        My Scoreboard &middot; {{ $this->scoreboardPresetLabel() }}
                    </p>

                    <div class="mb-2.5 flex flex-wrap gap-1" role="group" aria-label="Scoreboard date range">
                        @foreach ($this->scoreboardPresets as $preset)
                            <button
                                type="button"
                                wire:click="setScoreboardPreset('{{ $preset['key'] }}')"
                                @class([
                                    'rounded-full border px-2 py-0.5 text-[10px] font-semibold',
                                    'border-blue-600 bg-blue-600 text-white' => $this->scoreboardPreset === $preset['key'],
                                    'border-slate-300 bg-white text-slate-600 hover:border-blue-600 hover:text-blue-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400' => $this->scoreboardPreset !== $preset['key'],
                                ])
                            >
                                {{ $preset['label'] }}
                            </button>
                        @endforeach
                    </div>

                    <div class="flex flex-col gap-2">
                        @foreach ($this->scoreboardDefinitions as $definition)
                            @php
                                $metric = $this->scoreboard[$definition['key']] ?? ['count' => 0, 'percent' => null];
                            @endphp
                            <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-950">
                                <p class="m-0 text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $definition['label'] }}</p>
                                <p @class([
                                    'm-0 mt-1 text-xl font-extrabold leading-none',
                                    'text-emerald-700' => $definition['key'] === 'booked',
                                    'text-amber-600' => in_array($definition['key'], ['callbacks', 'overdue_callbacks'], true),
                                    'text-slate-900 dark:text-slate-100' => ! in_array($definition['key'], ['booked', 'callbacks', 'overdue_callbacks'], true),
                                ])>{{ number_format($metric['count'] ?? 0) }}</p>
                                <p class="m-0 mt-1 min-h-3.5 text-[10px] font-semibold text-slate-400 dark:text-slate-500">
                                    @if ($definition['show_percent'])
                                        {{ $this->formatScoreboardPercent($this->scoreboard, $definition['key']) }}
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                    <p class="m-0 mt-2 text-[10px] text-slate-400 dark:text-slate-500">Percentages are % of total leads called.</p>
                </div>

                <div x-show="tab === 'leaderboard'" x-cloak>
                    <p class="m-0 mb-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Leaderboard &middot; Today</p>
                    <ul class="m-0 flex list-none flex-col gap-1.5 p-0">
                        @forelse ($this->leaderboard as $row)
                            <li class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-sm dark:bg-slate-950">
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ $row['name'] }}</span>
                                <span class="font-bold text-emerald-700">{{ $row['bookings'] }}</span>
                            </li>
                        @empty
                            <li class="text-sm text-slate-400 dark:text-slate-500">No activity yet today.</li>
                        @endforelse
                    </ul>
                </div>

                <div x-show="tab === 'callbacks'" x-cloak>
                    <p class="m-0 mb-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">My Callbacks</p>
                    <ul class="m-0 flex max-h-72 list-none flex-col gap-1.5 overflow-y-auto p-0">
                        @forelse ($this->callbacks as $callback)
                            <li>
                                <button
                                    type="button"
                                    wire:click="openCallback({{ $callback->id }})"
                                    @class([
                                        'w-full rounded-md px-3 py-2 text-left text-sm border',
                                        'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-500/10' => $callback->callback_at?->isPast(),
                                        'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950' => ! $callback->callback_at?->isPast(),
                                    ])
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $callback->fullName() ?: $callback->phone }}</span>
                                        <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ \App\Support\CompanyTimezone::display($callback->callback_at) }}</span>
                                    </div>
                                    @if ($callback->callback_at?->isPast())
                                        <p class="m-0 mt-1 text-xs font-bold text-red-700">Overdue</p>
                                    @endif
                                </button>
                            </li>
                        @empty
                            <li class="text-sm text-slate-400 dark:text-slate-500">No scheduled callbacks.</li>
                        @endforelse
                    </ul>
                </div>

                <div x-show="tab === 'lookup'" x-cloak>
                    <p class="m-0 mb-1 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Lead Lookup</p>
                    <p class="m-0 mb-2.5 text-[11px] text-slate-400 dark:text-slate-500">Min {{ \App\Services\Leads\LeadLookupService::MIN_QUERY_LENGTH }} characters. Searches are not logged.</p>
                    <div class="flex gap-1.5">
                        <input
                            type="text"
                            wire:model="lookupQuery"
                            placeholder="Phone, name, or email"
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950"
                        >
                        <button
                            type="button"
                            wire:click="searchLeads"
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-950"
                        >
                            Search
                        </button>
                    </div>

                    @if ($lookupResults !== [])
                        <ul class="m-0 mt-2.5 flex list-none flex-col gap-1.5 p-0">
                            @foreach ($lookupResults as $result)
                                <li>
                                    <button
                                        type="button"
                                        wire:key="lookup-{{ $result['id'] }}"
                                        wire:click="selectLookupLead({{ $result['id'] }})"
                                        class="w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-950 dark:hover:bg-slate-800"
                                    >
                                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $result['name'] }}</span>
                                        <span class="text-slate-500 dark:text-slate-400"> — {{ $result['phone'] }}</span>
                                        <span class="ml-1.5 text-[11px] text-slate-400 dark:text-slate-500">{{ $result['status'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>