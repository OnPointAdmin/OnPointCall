<div class="space-y-6" wire:poll.10s>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">Get Next Lead</h2>
                <button
                    type="button"
                    wire:click="getNextLead"
                    class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                >
                    Get Next Lead
                </button>
            </div>

            @if ($emptyMessage)
                <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    {{ $emptyMessage }}
                </p>
            @endif

            @if ($lead)
                @include('livewire.agent.partials.lead-panel', [
                    'lead' => $lead,
                    'bookingUrl' => $this->bookingUrl,
                    'manualDialOnly' => $this->manualDialOnly,
                    'localTime' => $this->localTime,
                    'readOnly' => false,
                ])
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">My Scoreboard (Today)</h3>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-slate-500">Bookings</dt>
                        <dd class="text-2xl font-bold text-emerald-700">{{ $this->stats['bookings'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Calls</dt>
                        <dd class="text-2xl font-bold text-slate-900">{{ $this->stats['calls'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Skips</dt>
                        <dd class="text-2xl font-bold text-slate-700">{{ $this->stats['skips'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Callbacks</dt>
                        <dd class="text-2xl font-bold text-amber-700">{{ $this->stats['callbacks_pending'] }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Leaderboard (Today)</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($this->leaderboard as $row)
                        <li class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2">
                            <span class="font-medium text-slate-800">{{ $row['name'] }}</span>
                            <span class="text-emerald-700 font-semibold">{{ $row['bookings'] }} booked</span>
                        </li>
                    @empty
                        <li class="text-slate-500">No activity yet today.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">My Callbacks</h3>
            <ul class="mt-3 space-y-2">
                @forelse ($this->callbacks as $callback)
                    <li class="rounded-md border px-3 py-2 text-sm {{ $callback->callback_at?->isPast() ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-slate-50' }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium">{{ $callback->fullName() ?: $callback->phone }}</span>
                            <span class="text-xs text-slate-500">{{ $callback->callback_at?->format('M j, g:i A') }}</span>
                        </div>
                        @if ($callback->callback_at?->isPast())
                            <p class="mt-1 text-xs font-semibold text-red-700">Overdue</p>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No scheduled callbacks.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Lead Lookup</h3>
            <p class="mt-1 text-xs text-slate-500">Min {{ \App\Services\Leads\LeadLookupService::MIN_QUERY_LENGTH }} characters. Searches are not logged.</p>
            <div class="mt-3 flex gap-2">
                <input
                    type="text"
                    wire:model="lookupQuery"
                    placeholder="Phone, name, or email"
                    class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                >
                <button
                    type="button"
                    wire:click="searchLeads"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Search
                </button>
            </div>

            @if ($lookupResults->isNotEmpty())
                <ul class="mt-3 space-y-2">
                    @foreach ($lookupResults as $result)
                        <li>
                            <button
                                type="button"
                                wire:click="selectLookupLead({{ $result->id }})"
                                class="w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm hover:bg-slate-100"
                            >
                                <span class="font-medium">{{ $result->fullName() ?: 'Unknown' }}</span>
                                <span class="text-slate-500"> — {{ $result->phone }}</span>
                                <span class="ml-2 text-xs text-slate-400">{{ $result->status->label() }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($this->lookupLead && $lookupReadOnly)
                <div class="mt-4 border-t border-slate-200 pt-4">
                    @include('livewire.agent.partials.lead-readonly', ['lead' => $this->lookupLead])
                </div>
            @endif
        </div>
    </div>
</div>
