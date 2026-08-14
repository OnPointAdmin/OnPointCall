<?php

namespace App\Livewire\Agent;

use App\Enums\Disposition;
use App\Enums\EmptyQueueReason;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\SoftScoreStatus;
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
use App\Services\Qualification\QualificationService;
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

    public string $dispositionNote = '';

    /** @var array<string, mixed> */
    public array $editable = [];

    public string $lookupQuery = '';

    /** @var Collection<int, Lead> */
    public Collection $lookupResults;

    public ?int $lookupLeadId = null;

    public bool $lookupReadOnly = false;

    public string $softScoreMessage = '';

    public string $qualificationMessage = '';

    public bool $showSoftScoreRecentModal = false;

    public function mount(): void
    {
        $this->lookupResults = collect();

        $claim = app(LeadClaimService::class)->activeClaimForUser(Auth::guard('agent')->user());

        if ($claim?->lead) {
            $this->loadLead($claim->lead);
        }
    }

    public function getNextLead(): void
    {
        $this->resetDispositionFields();
        $this->editable = [];
        $this->lookupLeadId = null;
        $this->lookupReadOnly = false;
        $this->softScoreMessage = '';
        $this->qualificationMessage = '';
        $this->showSoftScoreRecentModal = false;

        $result = app(NextLeadService::class)->getNext(Auth::guard('agent')->user());

        if ($result->hasLead()) {
            $this->loadLead($result->lead);
            $this->emptyMessage = null;
        } else {
            $this->leadId = null;
            $this->emptyMessage = $result->emptyReason?->message()
                ?? EmptyQueueReason::NoneAvailable->message();
        }
    }

    public function startEdit(): void
    {
        $lead = $this->currentLead();

        if (! $lead) {
            return;
        }

        $this->editable = [
            'email' => $lead->email ?? '',
            'city' => $lead->city ?? '',
            'state' => $lead->state ?? '',
            'zip' => $lead->zip ?? '',
            'address' => $lead->address ?? '',
            'address_2' => $lead->address_2 ?? '',
            'age_range' => $lead->age_range ?? '',
            'annual_income' => $lead->annual_income ?? '',
            'marital_status' => $lead->marital_status ?? '',
            'gender' => $lead->gender ?? '',
            'home_owner' => $lead->home_owner ?? '',
        ];
    }

    public function cancelEdit(): void
    {
        $this->editable = [];
    }

    public function saveLeadEdits(): void
    {
        $lead = $this->currentLead();

        if (! $lead || $this->editable === []) {
            return;
        }

        $lead->update([
            'email' => $this->nullableString($this->editable['email'] ?? null),
            'city' => $this->nullableString($this->editable['city'] ?? null),
            'state' => $this->nullableString($this->editable['state'] ?? null),
            'zip' => $this->nullableString($this->editable['zip'] ?? null),
            'address' => $this->nullableString($this->editable['address'] ?? null),
            'address_2' => $this->nullableString($this->editable['address_2'] ?? null),
            'age_range' => $this->nullableString($this->editable['age_range'] ?? null),
            'annual_income' => $this->nullableString($this->editable['annual_income'] ?? null),
            'marital_status' => $this->nullableString($this->editable['marital_status'] ?? null),
            'gender' => $this->nullableString($this->editable['gender'] ?? null),
            'home_owner' => $this->nullableString($this->editable['home_owner'] ?? null),
        ]);

        $this->editable = [];
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

            $note = trim($this->dispositionNote);

            app(DispositionService::class)->apply(
                $lead,
                Auth::guard('agent')->user(),
                $disposition,
                $callbackAt,
                $disposition === Disposition::Skip ? trim($this->skipReason) : null,
                $disposition === Disposition::Skip || $note === '' ? null : $note,
            );

            $this->resetDispositionFields();
            $this->editable = [];
            $this->leadId = null;
            $this->softScoreMessage = '';
            $this->qualificationMessage = '';
            $this->showSoftScoreRecentModal = false;
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

        $softScore = app(SoftScoreService::class);

        if (! $softScore->shouldRun($lead)) {
            $this->showSoftScoreRecentModal = true;
            $this->softScoreMessage = '';

            return;
        }

        $softScore->scoreLead($lead, Auth::guard('agent')->id());

        $lead->refresh();

        $this->softScoreMessage = match ($lead->soft_score_status) {
            SoftScoreStatus::Error => 'Soft Score: Error'.($lead->soft_score_last_error ? ' — '.$lead->soft_score_last_error : ''),
            SoftScoreStatus::Pending => 'Soft Score: Pending',
            SoftScoreStatus::Complete => 'Soft Score: '.($lead->soft_score_code ?: '—'),
            SoftScoreStatus::Recent => 'Soft Score: Recently checked'.($lead->soft_score_code ? ' — '.$lead->soft_score_code : ''),
            default => 'Soft Score: Unknown',
        };
    }

    public function dismissSoftScoreRecentModal(): void
    {
        $this->showSoftScoreRecentModal = false;
    }

    public function runQualification(): void
    {
        $lead = $this->currentLead();

        if (! $lead) {
            return;
        }

        app(QualificationService::class)->qualifyLead($lead, Auth::guard('agent')->id());

        $lead->refresh();

        $this->qualificationMessage = match ($lead->qualification_status) {
            QualificationStatus::Error => 'Qualification: Error'.($lead->qualification_last_error ? ' — '.$lead->qualification_last_error : ''),
            QualificationStatus::Pending => 'Qualification: Pending',
            QualificationStatus::Qualified => 'Qualification: Qualified'.($lead->qualifiedPartnerNames() !== [] ? ' — '.implode(', ', $lead->qualifiedPartnerNames()) : ''),
            QualificationStatus::NotQualified => 'Qualification: Not qualified',
            default => 'Qualification: Unknown',
        };
    }

    public function searchLeads(): void
    {
        $this->lookupResults = app(LeadLookupService::class)
            ->search(Auth::guard('agent')->user()->company_id, $this->lookupQuery);

        $this->lookupLeadId = null;
        $this->lookupReadOnly = false;
    }

    public function selectLookupLead(int $leadId): void
    {
        $lead = Lead::withoutGlobalScopes()
            ->with([
                'callingList',
                'claim',
                'callbackOwner',
                'history' => fn ($q) => $this->callHistoryQuery($q),
            ])
            ->where('company_id', Auth::guard('agent')->user()->company_id)
            ->find($leadId);

        if (! $lead) {
            return;
        }

        $lookup = app(LeadLookupService::class);
        $this->lookupReadOnly = $lookup->isReadOnly($lead, Auth::guard('agent')->user());

        if ($lookup->canWorkImmediately($lead, Auth::guard('agent')->user())) {
            app(LeadClaimService::class)->claimForLookup($lead, Auth::guard('agent')->user());

            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => Auth::guard('agent')->id(),
                'event_type' => LeadHistoryType::Claim,
                'occurred_at' => now(),
                'payload' => ['source' => 'lookup'],
            ]);

            $this->loadLead($lead->fresh([
                'callingList',
                'claim',
                'history' => fn ($q) => $this->callHistoryQuery($q),
            ]));
            $this->lookupLeadId = null;
            $this->emptyMessage = null;
        } else {
            $this->lookupLeadId = $leadId;
        }
    }

    public function getStatsProperty(): array
    {
        return app(AgentStatsService::class)->statsForUser(Auth::guard('agent')->user());
    }

    public function getLeaderboardProperty(): Collection
    {
        return app(AgentStatsService::class)->leaderboard(Auth::guard('agent')->user()->company_id);
    }

    public function getCallbacksProperty(): Collection
    {
        return Lead::withoutGlobalScopes()
            ->with('callingList')
            ->where('company_id', Auth::guard('agent')->user()->company_id)
            ->where('status', LeadStatus::Callback)
            ->where('callback_owner_id', Auth::guard('agent')->id())
            ->orderBy('callback_at')
            ->get();
    }

    public function getLookupLeadProperty(): ?Lead
    {
        if (! $this->lookupLeadId) {
            return null;
        }

        return Lead::withoutGlobalScopes()
            ->with([
                'callingList',
                'history' => fn ($q) => $this->callHistoryQuery($q),
            ])
            ->where('company_id', Auth::guard('agent')->user()->company_id)
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
            ->with([
                'callingList',
                'claim',
                'history' => fn ($q) => $this->callHistoryQuery($q),
            ])
            ->where('company_id', Auth::guard('agent')->user()->company_id)
            ->find($this->leadId);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\LeadHistory, \App\Models\Lead>  $query
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\LeadHistory, \App\Models\Lead>
     */
    private function callHistoryQuery($query)
    {
        return $query->visibleInCallHistory()->with('actor')->orderByDesc('occurred_at')->limit(20);
    }

    private function loadLead(Lead $lead): void
    {
        $this->leadId = $lead->id;
        $this->editable = [];
        $this->softScoreMessage = '';
        $this->qualificationMessage = '';
        $this->showSoftScoreRecentModal = false;
    }

    private function resetDispositionFields(): void
    {
        $this->callbackAt = '';
        $this->skipReason = '';
        $this->dispositionNote = '';
        $this->resetErrorBag();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public function render()
    {
        return view('livewire.agent.workspace', [
            'lead' => $this->currentLead(),
        ]);
    }
}
