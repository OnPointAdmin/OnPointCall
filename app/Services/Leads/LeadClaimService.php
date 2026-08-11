<?php

namespace App\Services\Leads;

use App\Enums\LeadHistoryType;
use App\Models\Lead;
use App\Models\LeadClaim;
use App\Models\LeadHistory;
use App\Models\User;
use App\Services\Compliance\ComplianceService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class LeadClaimService
{
    public function __construct(
        private readonly ComplianceService $compliance,
    ) {}

    public function expireStaleClaims(int $companyId): int
    {
        $expired = LeadClaim::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;

        foreach ($expired as $claim) {
            DB::transaction(function () use ($claim): void {
                $claim->delete();

                LeadHistory::withoutGlobalScopes()->create([
                    'company_id' => $claim->company_id,
                    'lead_id' => $claim->lead_id,
                    'actor_id' => $claim->user_id,
                    'event_type' => LeadHistoryType::ClaimExpire,
                    'occurred_at' => now(),
                    'payload' => [
                        'user_id' => $claim->user_id,
                    ],
                ]);
            });

            $count++;
        }

        return $count;
    }

    public function createClaim(Lead $lead, User $user, ?int $ttlMinutes = null): LeadClaim
    {
        $ttlMinutes ??= $this->compliance->claimTtlMinutesFor($lead);
        $now = now();

        return LeadClaim::withoutGlobalScopes()->create([
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'claimed_at' => $now,
            'expires_at' => $now->copy()->addMinutes($ttlMinutes),
        ]);
    }

    public function releaseClaimForLead(Lead $lead, ?int $actorId = null): void
    {
        $claim = LeadClaim::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->first();

        if (! $claim) {
            return;
        }

        $claim->delete();

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'actor_id' => $actorId,
            'event_type' => LeadHistoryType::ClaimExpire,
            'occurred_at' => now(),
            'payload' => [
                'released' => true,
            ],
        ]);
    }

    public function activeClaimForUser(User $user): ?LeadClaim
    {
        return LeadClaim::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->with('lead')
            ->first();
    }

    public function isLeased(Lead $lead, ?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return LeadClaim::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('expires_at', '>', $at)
            ->exists();
    }

    public function isLeasedToUser(Lead $lead, User $user, ?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return LeadClaim::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('user_id', $user->id)
            ->where('expires_at', '>', $at)
            ->exists();
    }

    public function claimForLookup(Lead $lead, User $user): ?LeadClaim
    {
        if ($this->isLeased($lead) && ! $this->isLeasedToUser($lead, $user)) {
            return null;
        }

        if ($this->isLeasedToUser($lead, $user)) {
            return LeadClaim::withoutGlobalScopes()
                ->where('lead_id', $lead->id)
                ->where('user_id', $user->id)
                ->where('expires_at', '>', now())
                ->first();
        }

        return $this->createClaim($lead, $user);
    }
}
