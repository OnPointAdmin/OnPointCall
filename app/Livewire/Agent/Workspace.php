<?php

namespace App\Livewire\Agent;

use App\Enums\Disposition;
use App\Enums\EmptyQueueReason;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Exceptions\CallbackOutsideWindowException;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Services\Compliance\ComplianceService;
use App\Services\Leads\AgentStatsService;
use App\Services\Leads\BookingUrlBuilder;
use App\Services\Leads\DispositionService;
use App\Services\Leads\LeadClaimService;
use App\Services\Leads\LeadLookupService;
use App\Services\Leads\NextLeadService;
use App\Services\SoftScore\SoftScoreService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.agent')]
class Workspace extends Component
{
    public ?int $leadId = null;

    public ?string $emptyMessage = null;

    public string $callbackAt = '';

    public string $skipReason = '';

    public string $lookupQuery = '';

    /** @var Collection<int, Lead> */
    public Collection $lookupResults;

    public ?int $lookupLeadId = null;

    public bool $lookupReadOnly = false;

    public string $softScoreMessage = '';

    public function mount(): void
    {
        $this->lookupResults = collect();

        $claim = app(LeadClaimService::class)->activeClaimForUser(Auth::user());

        if ($claim?->lead) {
            $this->loadLead($claim->lead);
        }
    }

    public function getNextLead(): void
    {
        $this->resetDispositionFields();
        $this->lookupLeadId = null;
        $this->lookupReadOnly = false;
        $this->softScoreMessage = '';

        $result = app(NextLeadService::class)->getNext(Auth::user());

        if ($result->hasLead()) {
            $this->loadLead($result->lead);
            $this->emptyMessage = null;
        } else {
            $this->leadId = null;
            $this->emptyMessage = $result->emptyReason?->message()
                ?? EmptyQueueReason::NoneAvailable->message();
        }
    }

    public function applyDisposition(string $dispositionValue): void
    {
        if (! $this->leadId) {
            return;
        }

        $lead = $this->currentLead();

        if (! $lead) {
            return;
        }

        $disposition = Disposition::from($dispositionValue);

        try {
            $callbackAt = null;

            if ($disposition === Disposition::Callback) {
                $callbackAt = Carbon::parse($this->callbackAt);
            }

            if ($disposition === Disposition::Skip && trim($this->skipReason) === '') {
                $this->addError('skipReason', 'A skip reason is required.');

                return;
            }

            app(DispositionService::class)->apply(
                $lead,
                Auth::user(),
                $disposition,
                $callbackAt,
                $disposition === Disposition::Skip ? trim($this->skipReason) : null,
            );

            $this->resetDispositionFields();
            $this->leadId = null;
            $this->softScoreMessage = '';
            $this->dispatch('stats-updated');
        } catch (CallbackOutsideWindowException) {
            $this->addError('callbackAt', 'Callback must fall within legal calling hours for this lead.');
        }
    }

    public function runSoftScore(): void
    {
        $lead = $this->currentLead();

        if (! $lead) {
            return;
        }

        app(SoftScoreService::class)->scoreLead($lead, Auth::id());

        $lead->refresh();

        $status = $lead->soft_score_status?->label() ?? 'Unknown';
        $code = $lead->soft_score_code ?? '—';

        $this->softScoreMessage = "Soft Score: {$status} ({$code})";
    }

    public function searchLeads(): void
    {
        $this->lookupResults = app(LeadLookupService::class)
            ->search(Auth::user()->company_id, $this->lookupQuery);

        $this->lookupLeadId = null;
        $this->lookupReadOnly = false;
    }

    public function selectLookupLead(int $leadId): void
    {
        $lead = Lead::withoutGlobalScopes()
            ->with(['callingList', 'claim', 'callbackOwner', 'history' => fn ($q) => $q->orderByDesc('occurred_at')->limit(20)])
            ->where('company_id', Auth::user()->company_id)
            ->find($leadId);

        if (! $lead) {
            return;
        }

        $lookup = app(LeadLookupService::class);
        $this->lookupReadOnly = $lookup->isReadOnly($lead, Auth::user());

        if ($lookup->canWorkImmediately($lead, Auth::user())) {
            app(LeadClaimService::class)->claimForLookup($lead, Auth::user());

            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => Auth::id(),
                'event_type' => LeadHistoryType::Claim,
                'occurred_at' => now(),
                'payload' => ['source' => 'lookup'],
            ]);

            $this->loadLead($lead->fresh(['callingList', 'claim', 'history']));
            $this->lookupLeadId = null;
            $this->emptyMessage = null;
        } else {
            $this->lookupLeadId = $leadId;
        }
    }

    public function getStatsProperty(): array
    {
        return app(AgentStatsService::class)->statsForUser(Auth::user());
    }

    public function getLeaderboardProperty(): Collection
    {
        return app(AgentStatsService::class)->leaderboard(Auth::user()->company_id);
    }

    public function getCallbacksProperty(): Collection
    {
        return Lead::withoutGlobalScopes()
            ->with('callingList')
            ->where('company_id', Auth::user()->company_id)
            ->where('status', LeadStatus::Callback)
            ->where('callback_owner_id', Auth::id())
            ->orderBy('callback_at')
            ->get();
    }

    public function getLookupLeadProperty(): ?Lead
    {
        if (! $this->lookupLeadId) {
            return null;
        }

        return Lead::withoutGlobalScopes()
            ->with(['callingList', 'history' => fn ($q) => $q->orderByDesc('occurred_at')->limit(20)])
            ->where('company_id', Auth::user()->company_id)
            ->find($this->lookupLeadId);
    }

    public function getBookingUrlProperty(): ?string
    {
        $lead = $this->currentLead();

        return $lead ? app(BookingUrlBuilder::class)->build($lead) : null;
    }

    public function getManualDialOnlyProperty(): bool
    {
        $lead = $this->currentLead();

        return $lead ? app(ComplianceService::class)->isManualDialOnly($lead) : false;
    }

    public function getLocalTimeProperty(): ?string
    {
        $lead = $this->currentLead();

        if (! $lead?->timezone) {
            return null;
        }

        return now()->timezone($lead->timezone)->format('g:i A T');
    }

    private function currentLead(): ?Lead
    {
        if (! $this->leadId) {
            return null;
        }

        return Lead::withoutGlobalScopes()
            ->with(['callingList', 'claim', 'history' => fn ($q) => $q->orderByDesc('occurred_at')->limit(20)])
            ->where('company_id', Auth::user()->company_id)
            ->find($this->leadId);
    }

    private function loadLead(Lead $lead): void
    {
        $this->leadId = $lead->id;
    }

    private function resetDispositionFields(): void
    {
        $this->callbackAt = '';
        $this->skipReason = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.agent.workspace', [
            'lead' => $this->currentLead(),
        ]);
    }
}
