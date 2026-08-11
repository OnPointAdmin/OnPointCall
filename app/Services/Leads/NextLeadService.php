<?php

namespace App\Services\Leads;

use App\DataTransferObjects\NextLeadResult;
use App\Enums\EmptyQueueReason;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Services\Compliance\ComplianceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NextLeadService
{
    public function __construct(
        private readonly ComplianceService $compliance,
        private readonly LeadClaimService $claimService,
    ) {}

    public function getNext(User $user): NextLeadResult
    {
        $listIds = $this->assignedListIds($user);

        if ($listIds === []) {
            return new NextLeadResult(emptyReason: EmptyQueueReason::NoneAvailable);
        }

        $this->claimService->expireStaleClaims($user->company_id);

        $existing = $this->claimService->activeClaimForUser($user);

        if ($existing?->lead) {
            return new NextLeadResult(lead: $existing->lead->load(['callingList', 'claim']));
        }

        $lead = $this->claimNextLead($user, $listIds);

        if ($lead) {
            return new NextLeadResult(lead: $lead);
        }

        return new NextLeadResult(emptyReason: $this->diagnoseEmptyQueue($user, $listIds));
    }

    /**
     * @param  list<int>  $listIds
     */
    private function claimNextLead(User $user, array $listIds): ?Lead
    {
        $callbackLead = $this->tryClaimFromCandidates(
            $user,
            $this->callbackCandidates($user, $listIds),
        );

        if ($callbackLead) {
            return $callbackLead;
        }

        return $this->tryClaimFromCandidates(
            $user,
            $this->poolCandidates($user, $listIds),
        );
    }

    /**
     * @param  list<int>  $listIds
     * @return Collection<int, Lead>
     */
    private function callbackCandidates(User $user, array $listIds): Collection
    {
        return Lead::withoutGlobalScopes()
            ->with('callingList')
            ->where('company_id', $user->company_id)
            ->where('status', LeadStatus::Callback)
            ->where('callback_owner_id', $user->id)
            ->where('callback_at', '<=', now())
            ->whereIn('calling_list_id', $listIds)
            ->whereDoesntHave('claim', fn ($query) => $query->where('expires_at', '>', now()))
            ->orderBy('callback_at')
            ->limit(25)
            ->get();
    }

    /**
     * @param  list<int>  $listIds
     * @return Collection<int, Lead>
     */
    private function poolCandidates(User $user, array $listIds): Collection
    {
        return Lead::withoutGlobalScopes()
            ->with('callingList')
            ->where('company_id', $user->company_id)
            ->where('status', LeadStatus::Callable)
            ->whereIn('calling_list_id', $listIds)
            ->whereDoesntHave('claim', fn ($query) => $query->where('expires_at', '>', now()))
            ->orderBy('queue_rank')
            ->orderBy('imported_at')
            ->limit(50)
            ->get()
            ->filter(fn (Lead $lead): bool => $this->compliance->hasAttemptsRemaining($lead))
            ->values();
    }

    /**
     * @param  Collection<int, Lead>  $candidates
     */
    private function tryClaimFromCandidates(User $user, Collection $candidates): ?Lead
    {
        foreach ($candidates as $candidate) {
            if (! $this->compliance->canDialNow($candidate)) {
                continue;
            }

            $lead = DB::transaction(function () use ($user, $candidate): ?Lead {
                $lead = Lead::withoutGlobalScopes()
                    ->with('callingList')
                    ->whereKey($candidate->id)
                    ->lock('for update skip locked')
                    ->first();

                if (! $lead || $this->claimService->isLeased($lead)) {
                    return null;
                }

                if (! $this->compliance->canDialNow($lead)) {
                    return null;
                }

                $this->claimService->createClaim($lead, $user);

                LeadHistory::withoutGlobalScopes()->create([
                    'company_id' => $lead->company_id,
                    'lead_id' => $lead->id,
                    'actor_id' => $user->id,
                    'event_type' => LeadHistoryType::Claim,
                    'occurred_at' => now(),
                    'payload' => [],
                ]);

                return $lead->fresh(['callingList', 'claim']);
            });

            if ($lead) {
                return $lead;
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $listIds
     */
    private function diagnoseEmptyQueue(User $user, array $listIds): EmptyQueueReason
    {
        $leads = Lead::withoutGlobalScopes()
            ->with('callingList')
            ->where('company_id', $user->company_id)
            ->whereIn('calling_list_id', $listIds)
            ->where(function ($query) use ($user): void {
                $query->where(function ($callable): void {
                    $callable->where('status', LeadStatus::Callable);
                })->orWhere(function ($callback) use ($user): void {
                    $callback->where('status', LeadStatus::Callback)
                        ->where('callback_owner_id', $user->id)
                        ->where('callback_at', '<=', now());
                });
            })
            ->whereDoesntHave('claim', fn ($query) => $query->where('expires_at', '>', now()))
            ->get()
            ->filter(fn (Lead $lead): bool => $lead->status !== LeadStatus::Callable || $this->compliance->hasAttemptsRemaining($lead));

        if ($leads->isEmpty()) {
            return EmptyQueueReason::NoneAvailable;
        }

        $inHours = $leads->filter(fn (Lead $lead): bool => $this->compliance->isWithinLegalWindow($lead));

        if ($inHours->isEmpty()) {
            return EmptyQueueReason::BlockedByHours;
        }

        $cadenceReady = $inHours->filter(fn (Lead $lead): bool => $this->compliance->isCadenceReady($lead));

        if ($cadenceReady->isEmpty()) {
            return EmptyQueueReason::BlockedByCadence;
        }

        return EmptyQueueReason::NoneAvailable;
    }

    /**
     * @return list<int>
     */
    private function assignedListIds(User $user): array
    {
        return $user->listAssignments()
            ->pluck('calling_list_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
