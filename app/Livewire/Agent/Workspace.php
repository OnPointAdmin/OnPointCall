<?php

namespace App\Livewire\Agent;

use App\Enums\Disposition;
use App\Enums\EmptyQueueReason;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\SoftScoreStatus;
use App\Exceptions\CallbackOutsideWindowException;
use App\Exceptions\MissingDispositionReasonException;
use App\Jobs\QualifyLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Models\DispositionReason;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Services\Compliance\ComplianceService;
use App\Services\Dashboard\ManagerDashboardService;
use App\Services\Leads\AgentStatsService;
use App\Services\Leads\BookingUrlBuilder;
use App\Services\Leads\DispositionService;
use App\Services\Leads\LeadClaimService;
use App\Services\Leads\LeadLookupService;
use App\Services\Leads\NextLeadService;
use App\Services\Qualification\QualificationService;
use App\Services\SoftScore\SoftScoreService;
use App\Support\CompanyTimezone;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.agent')]
class Workspace extends Component
{
    private const EDITABLE_FIELDS = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'address',
        'address_2',
        'city',
        'state',
        'zip',
        'age_range',
        'annual_income',
        'marital_status',
        'gender',
        'home_owner',
    ];

    private const SOFT_SCORE_TRIGGER_FIELDS = [
        'first_name',
        'last_name',
        'phone',
        'address',
        'address_2',
        'city',
        'state',
        'zip',
    ];

    private const QUALIFICATION_TRIGGER_FIELDS = [
        'address',
        'address_2',
        'city',
        'state',
        'zip',
        'age_range',
        'annual_income',
        'marital_status',
        'gender',
        'home_owner',
    ];

    public ?int $leadId = null;

    public ?string $emptyMessage = null;

    public string $callbackAt = '';

    public string $dispositionNote = '';

    public ?string $dispositionReasonId = null;

    /** @var array<string, mixed> */
    public array $editable = [];

    public string $lookupQuery = '';

    /** @var list<array{id: int, name: string, phone: ?string, status: string}> */
    public array $lookupResults = [];

    public ?int $lookupLeadId = null;

    public bool $lookupReadOnly = false;

    public bool $leadReadOnly = false;

    public string $leadReadOnlyMessage = '';

    public string $softScoreMessage = '';

    public string $qualificationMessage = '';

    public bool $showSoftScoreRecentModal = false;

    public string $scoreboardPreset = 'today';

    public function mount(): void
    {
        $this->lookupResults = [];

        $claim = app(LeadClaimService::class)->activeClaimForUser(Auth::guard('agent')->user());

        if ($claim?->lead) {
            $this->leadReadOnly = false;
            $this->leadReadOnlyMessage = '';
            $this->loadLead($claim->lead);
        }
    }

    public function getNextLead(): void
    {
        $this->resetDispositionFields();
        $this->editable = [];
        $this->lookupLeadId = null;
        $this->lookupReadOnly = false;
        $this->leadReadOnly = false;
        $this->leadReadOnlyMessage = '';
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
            'first_name' => $lead->first_name ?? '',
            'last_name' => $lead->last_name ?? '',
            'phone' => $lead->phone ?? '',
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

        if (! $lead || $this->editable === [] || $this->leadReadOnly) {
            return;
        }

        $updates = [];
        $changes = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            $new = $field === 'phone'
                ? $this->normalizedPhone($this->editable['phone'] ?? null)
                : $this->nullableString($this->editable[$field] ?? null);

            if ($field === 'phone') {
                $rawPhone = $this->nullableString($this->editable['phone'] ?? null);

                if ($rawPhone !== null && $new === null) {
                    $this->addError('editable.phone', 'Enter a valid 10-digit phone number.');

                    return;
                }
            }

            $old = $lead->{$field};
            $oldValue = is_string($old) ? $old : null;

            if ((string) ($oldValue ?? '') !== (string) ($new ?? '')) {
                $updates[$field] = $new;
                $changes[$field] = [
                    'from' => $oldValue,
                    'to' => $new,
                ];
            }
        }

        if ($changes === []) {
            $this->editable = [];

            return;
        }

        $lead->update($updates);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'actor_id' => Auth::guard('agent')->id(),
            'event_type' => LeadHistoryType::FieldEdit,
            'occurred_at' => now(),
            'payload' => ['changes' => $changes],
        ]);

        $changedFields = array_keys($changes);
        $forceSoftScore = array_intersect($changedFields, self::SOFT_SCORE_TRIGGER_FIELDS) !== [];
        $forceQualification = $forceSoftScore
            || array_intersect($changedFields, self::QUALIFICATION_TRIGGER_FIELDS) !== [];

        $this->queueScoreAndQualification($lead, $forceSoftScore, $forceQualification, force: true);

        $this->editable = [];
    }

    public function applyDisposition(string $dispositionValue): void
    {
        if (! $this->leadId || $this->leadReadOnly) {
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
                if (trim($this->callbackAt) === '') {
                    $this->addError('callbackAt', 'Callback date/time is required.');

                    return;
                }

                $callbackAt = CompanyTimezone::parse(
                    $this->callbackAt,
                    Auth::guard('agent')->user()->company_id,
                );
            }

            $reason = null;

            if (in_array($disposition, [Disposition::NotInterested, Disposition::NotQualified, Disposition::Skip], true)) {
                $reason = trim((string) $this->dispositionReasonId);

                if ($reason === '') {
                    $this->addError('dispositionReasonId', 'A reason is required.');

                    return;
                }
            }

            $note = trim($this->dispositionNote);

            app(DispositionService::class)->apply(
                $lead,
                Auth::guard('agent')->user(),
                $disposition,
                $callbackAt,
                $note === '' ? null : $note,
                $reason,
            );

            $this->resetDispositionFields();
            $this->editable = [];
            $this->leadId = null;
            $this->leadReadOnly = false;
            $this->leadReadOnlyMessage = '';
            $this->softScoreMessage = '';
            $this->qualificationMessage = '';
            $this->showSoftScoreRecentModal = false;
            $this->dispatch('stats-updated');
        } catch (CallbackOutsideWindowException) {
            $this->addError('callbackAt', 'Callback must fall within legal calling hours for this lead.');
        } catch (MissingDispositionReasonException) {
            $this->addError('dispositionReasonId', 'Select a valid reason for this disposition.');
        }
    }

    public function putBackCallback(): void
    {
        $user = Auth::guard('agent')->user();
        $lead = $this->currentLead();

        if (! $lead || $lead->status !== LeadStatus::Callback || $lead->callback_owner_id !== $user->id) {
            return;
        }

        app(LeadClaimService::class)->releaseClaimForLead($lead, $user->id);

        $this->resetDispositionFields();
        $this->editable = [];
        $this->lookupLeadId = null;
        $this->lookupReadOnly = false;
        $this->softScoreMessage = '';
        $this->qualificationMessage = '';
        $this->showSoftScoreRecentModal = false;

        $remaining = app(LeadClaimService::class)->activeClaimForUser($user);

        if ($remaining?->lead && $remaining->lead_id !== $lead->id) {
            $this->leadReadOnly = false;
            $this->leadReadOnlyMessage = '';
            $this->loadLead($remaining->lead->load([
                'callingList',
                'claim',
                'history' => fn ($q) => $this->callHistoryQuery($q),
            ]));
            $this->emptyMessage = null;

            return;
        }

        $this->leadId = null;
        $this->leadReadOnly = false;
        $this->leadReadOnlyMessage = '';
        $this->emptyMessage = 'Callback kept on your list. Open it again when you are ready to call.';
    }

    public function runSoftScore(): void
    {
        $lead = $this->currentLead();

        if (! $lead || $this->leadReadOnly) {
            return;
        }

        $softScore = app(SoftScoreService::class);

        if (! $softScore->shouldShowRunButton($lead)) {
            $this->showSoftScoreRecentModal = false;
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

        if (! $lead || $this->leadReadOnly) {
            return;
        }

        $qualification = app(QualificationService::class);

        if (! $qualification->shouldShowRunButton($lead)) {
            return;
        }

        $qualification->qualifyLead($lead, Auth::guard('agent')->id());

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
            ->search(Auth::guard('agent')->user()->company_id, $this->lookupQuery)
            ->map(fn (Lead $lead): array => [
                'id' => $lead->id,
                'name' => $lead->fullName() ?: 'Unknown',
                'phone' => $lead->phone,
                'status' => $lead->status->label(),
            ])
            ->values()
            ->all();

        $this->lookupLeadId = null;
        $this->lookupReadOnly = false;
    }

    public function selectLookupLead(int $leadId): void
    {
        $user = Auth::guard('agent')->user();

        $lead = Lead::withoutGlobalScopes()
            ->with([
                'callingList',
                'claim',
                'callbackOwner',
                'history' => fn ($q) => $this->callHistoryQuery($q),
            ])
            ->where('company_id', $user->company_id)
            ->find($leadId);

        if (! $lead) {
            return;
        }

        $this->resetDispositionFields();
        $this->editable = [];
        $this->lookupLeadId = null;
        $this->lookupReadOnly = false;
        $this->softScoreMessage = '';
        $this->qualificationMessage = '';
        $this->showSoftScoreRecentModal = false;
        $this->emptyMessage = null;

        if ($lead->status === LeadStatus::Dnc) {
            $this->leadReadOnly = true;
            $this->leadReadOnlyMessage = 'DNC — this lead cannot be worked';
            $this->loadLead($lead);

            return;
        }

        $claim = app(LeadClaimService::class)->claimForLookup($lead, $user);

        if ($claim) {
            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => $user->id,
                'event_type' => LeadHistoryType::Claim,
                'occurred_at' => now(),
                'payload' => ['source' => 'lookup'],
            ]);
        }

        $this->leadReadOnly = false;
        $this->leadReadOnlyMessage = '';
        $this->loadLead($lead->fresh([
            'callingList',
            'claim',
            'history' => fn ($q) => $this->callHistoryQuery($q),
        ]));
    }

    public function openCallback(int $leadId): void
    {
        $user = Auth::guard('agent')->user();

        $lead = Lead::withoutGlobalScopes()
            ->with([
                'callingList',
                'claim',
                'history' => fn ($q) => $this->callHistoryQuery($q),
            ])
            ->where('company_id', $user->company_id)
            ->where('status', LeadStatus::Callback)
            ->where('callback_owner_id', $user->id)
            ->find($leadId);

        if (! $lead) {
            return;
        }

        $this->resetDispositionFields();
        $this->editable = [];
        $this->lookupLeadId = null;
        $this->lookupReadOnly = false;
        $this->softScoreMessage = '';
        $this->qualificationMessage = '';
        $this->showSoftScoreRecentModal = false;

        $lookup = app(LeadLookupService::class);

        if ($lookup->canWorkImmediately($lead, $user)) {
            app(LeadClaimService::class)->claimForLookup($lead, $user);

            $this->leadReadOnly = false;
            $this->leadReadOnlyMessage = '';
            $this->loadLead($lead->fresh([
                'callingList',
                'claim',
                'history' => fn ($q) => $this->callHistoryQuery($q),
            ]));
            $this->emptyMessage = null;

            return;
        }

        $this->leadReadOnly = true;
        $this->leadReadOnlyMessage = app(ComplianceService::class)->isWithinLegalWindow($lead)
            ? 'This callback cannot be worked right now — read only'
            : 'Outside legal calling hours — read only';
        $this->loadLead($lead);
        $this->emptyMessage = null;
    }

    public function setScoreboardPreset(string $preset): void
    {
        $allowed = array_column($this->scoreboardPresets, 'key');

        if (! in_array($preset, $allowed, true)) {
            return;
        }

        $this->scoreboardPreset = $preset;
    }

    /**
     * @return array<string, array{label: string, count: int, percent: ?float}>
     */
    public function getScoreboardProperty(): array
    {
        return app(AgentStatsService::class)->scoreboardForUser(
            Auth::guard('agent')->user(),
            $this->scoreboardPreset,
        );
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function getScoreboardPresetsProperty(): array
    {
        return app(AgentStatsService::class)->scoreboardDatePresets();
    }

    /**
     * @return list<array{key: string, label: string, show_percent: bool}>
     */
    public function getScoreboardDefinitionsProperty(): array
    {
        return app(ManagerDashboardService::class)->metricDefinitions();
    }

    public function scoreboardPresetLabel(): string
    {
        foreach ($this->scoreboardPresets as $preset) {
            if ($preset['key'] === $this->scoreboardPreset) {
                return $preset['label'];
            }
        }

        return 'Today';
    }

    public function formatScoreboardPercent(array $metrics, string $key): string
    {
        return app(ManagerDashboardService::class)->formatPercent($metrics, $key);
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
     * @param  HasMany<LeadHistory, Lead>  $query
     * @return HasMany<LeadHistory, Lead>
     */
    private function callHistoryQuery($query)
    {
        $user = Auth::guard('agent')->user();

        $query->visibleInCallHistory()->with('actor')->orderByDesc('occurred_at')->limit(20);

        if (! $user->role->canAccessAdmin()) {
            $query->where('actor_id', $user->id);
        }

        return $query;
    }

    private function loadLead(Lead $lead): void
    {
        $this->leadId = $lead->id;
        $this->editable = [];
        $this->softScoreMessage = '';
        $this->qualificationMessage = '';
        $this->showSoftScoreRecentModal = false;

        $needSoftScore = app(SoftScoreService::class)->isBlank($lead);
        $needQualification = app(QualificationService::class)->isBlank($lead);

        $this->queueScoreAndQualification($lead, $needSoftScore, $needQualification, force: false);
    }

    private function queueScoreAndQualification(Lead $lead, bool $runSoftScore, bool $runQualification, bool $force): void
    {
        if (! $runSoftScore && ! $runQualification) {
            return;
        }

        $actorId = Auth::guard('agent')->id();

        if ($runSoftScore && $runQualification) {
            $lead->update(['qualification_status' => QualificationStatus::Pending]);
        }

        if ($runSoftScore) {
            if (! $force) {
                $lead->update(['soft_score_status' => SoftScoreStatus::Pending]);
            }

            SoftScoreLeadJob::dispatch(
                $lead->id,
                $lead->import_batch_id,
                $actorId,
                $runQualification,
                $force,
            );

            return;
        }

        if (! $force) {
            $lead->update(['qualification_status' => QualificationStatus::Pending]);
        }

        QualifyLeadJob::dispatch($lead->id, $lead->import_batch_id, $actorId, $force);
    }

    /**
     * @return Collection<int, DispositionReason>
     */
    private function reasonsFor(Disposition $disposition): Collection
    {
        return DispositionReason::withoutGlobalScopes()
            ->where('company_id', Auth::guard('agent')->user()->company_id)
            ->activeFor($disposition)
            ->get();
    }

    private function resetDispositionFields(): void
    {
        $this->callbackAt = '';
        $this->dispositionNote = '';
        $this->dispositionReasonId = null;
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

    private function normalizedPhone(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return PhoneNormalizer::normalize($value);
    }

    public function render()
    {
        $lead = $this->currentLead();
        $overdueCallbackCount = $this->callbacks
            ->filter(fn (Lead $callback) => $callback->callback_at?->isPast())
            ->count();

        return view('livewire.agent.workspace', [
            'lead' => $lead,
            'canRunSoftScore' => $lead ? app(SoftScoreService::class)->shouldShowRunButton($lead) : false,
            'canRunQualification' => $lead ? app(QualificationService::class)->shouldShowRunButton($lead) : false,
            'overdueCallbackCount' => $overdueCallbackCount,
            'defaultSecondaryTab' => $overdueCallbackCount > 0 ? 'callbacks' : 'scoreboard',
            'notInterestedReasons' => $this->reasonsFor(Disposition::NotInterested),
            'notQualifiedReasons' => $this->reasonsFor(Disposition::NotQualified),
            'skipReasons' => $this->reasonsFor(Disposition::Skip),
            'agentTimezone' => CompanyTimezone::for(Auth::guard('agent')->user()->company_id),
        ]);
    }
}
