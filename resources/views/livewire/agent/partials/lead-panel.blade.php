<div class="mt-4 space-y-4">
    @if (in_array($lead->status->value, ['booked', 'terminal', 'dnc']))
        <div class="rounded-md border border-red-300 bg-red-50 p-3 text-sm font-semibold text-red-800">
            {{ $lead->status->label() }} — read only
        </div>
    @endif

    <div>
        @include('livewire.agent.partials.phone-display', [
            'phone' => $lead->phone,
            'manualDialOnly' => $manualDialOnly,
        ])
    </div>

    <div class="grid gap-3 sm:grid-cols-2 text-sm">
        <div>
            <p class="text-slate-500">Name</p>
            <p class="select-none font-medium text-slate-900" oncopy="return false">{{ $lead->fullName() }}</p>
        </div>
        <div>
            <p class="text-slate-500">Location</p>
            <p class="select-none text-slate-900" oncopy="return false">{{ $lead->city }}, {{ $lead->state }}</p>
        </div>
        @if ($localTime)
            <div>
                <p class="text-slate-500">Lead local time</p>
                <p class="text-slate-900">{{ $localTime }}</p>
            </div>
        @endif
        <div>
            <p class="text-slate-500">Venue / Event</p>
            <p class="select-none text-slate-900" oncopy="return false">{{ $lead->venue }} / {{ $lead->event }}</p>
        </div>
        <div>
            <p class="text-slate-500">Partners</p>
            <p class="select-none text-slate-900" oncopy="return false">{{ $lead->partner_list }}</p>
        </div>
        @if ($lead->file_name)
            <div>
                <p class="text-slate-500">Source file</p>
                <p class="select-none text-slate-900" oncopy="return false">{{ $lead->file_name }}</p>
            </div>
        @endif
        <div>
            <p class="text-slate-500">Attempts</p>
            <p class="text-slate-900">{{ $lead->attempt_count }}</p>
        </div>
        @foreach (\App\Support\LeadDisplayFields::agentFields($lead) as $field)
            <div>
                <p class="text-slate-500">{{ $field['label'] }}</p>
                <p class="select-none text-slate-900" oncopy="return false">{{ $field['value'] }}</p>
            </div>
        @endforeach
    </div>

  @if ($lead->soft_score_status)
        <div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
            <span class="text-slate-500">Soft Score:</span>
            <span class="font-medium">{{ $lead->soft_score_status->label() }}</span>
            @if ($lead->soft_score_code)
                <span class="text-slate-700">({{ $lead->soft_score_code }})</span>
            @endif
        </div>
    @endif

    @if ($softScoreMessage)
        <p class="text-sm text-slate-700">{{ $softScoreMessage }}</p>
    @endif

    @unless ($readOnly)
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="runSoftScore"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Run Soft Score
            </button>
            @if ($bookingUrl)
                <a
                    href="{{ $bookingUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                >
                    Open Booking Form
                </a>
            @endif
        </div>

        <div class="space-y-3 border-t border-slate-200 pt-4">
            <h4 class="text-sm font-semibold text-slate-900">Disposition</h4>
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Enums\Disposition::cases() as $disposition)
                    @if ($disposition !== \App\Enums\Disposition::Skip)
                        <button
                            type="button"
                            wire:click="applyDisposition('{{ $disposition->value }}')"
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            {{ $disposition->label() }}
                        </button>
                    @endif
                @endforeach
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Callback date/time</label>
                    <input
                        type="datetime-local"
                        wire:model="callbackAt"
                        class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    >
                    @error('callbackAt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Skip reason</label>
                    <input
                        type="text"
                        wire:model="skipReason"
                        placeholder="Required for skip"
                        class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    >
                    @error('skipReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <button
                type="button"
                wire:click="applyDisposition('skip')"
                class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
            >
                Skip (bottom of queue)
            </button>
        </div>
    @endunless

    @if ($lead->history->isNotEmpty())
        <div class="border-t border-slate-200 pt-4">
            <h4 class="text-sm font-semibold text-slate-900">History</h4>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($lead->history as $entry)
                    <li class="rounded-md bg-slate-50 px-3 py-2">
                        <span class="font-medium">{{ $entry->event_type->label() }}</span>
                        <span class="text-slate-500"> — {{ $entry->occurred_at?->format('M j, g:i A') }}</span>
                        @if ($entry->payload)
                            <span class="text-slate-600"> {{ json_encode($entry->payload) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
