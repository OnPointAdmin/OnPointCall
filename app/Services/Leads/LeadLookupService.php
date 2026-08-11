<?php

namespace App\Services\Leads;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\Compliance\ComplianceService;
use Illuminate\Support\Collection;

class LeadLookupService
{
    public const MIN_QUERY_LENGTH = 3;

    public const MAX_RESULTS = 10;

    public function __construct(
        private readonly ComplianceService $compliance,
        private readonly LeadClaimService $claimService,
    ) {}

    /**
     * @return Collection<int, Lead>
     */
    public function search(int $companyId, string $query): Collection
    {
        $query = trim($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        $digits = preg_replace('/\D/', '', $query) ?? '';

        return Lead::withoutGlobalScopes()
            ->with(['callingList', 'claim', 'callbackOwner'])
            ->where('company_id', $companyId)
            ->where(function ($builder) use ($query, $digits): void {
                if ($digits !== '' && strlen($digits) >= 3) {
                    $builder->where('phone', 'like', "%{$digits}%");
                }

                $builder->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(self::MAX_RESULTS)
            ->get();
    }

    public function isReadOnly(Lead $lead, User $user): bool
    {
        if (in_array($lead->status, [LeadStatus::Booked, LeadStatus::Terminal, LeadStatus::Dnc], true)) {
            return true;
        }

        if ($lead->status === LeadStatus::Callback && $lead->callback_owner_id !== $user->id) {
            return true;
        }

        if ($this->claimService->isLeased($lead) && ! $this->claimService->isLeasedToUser($lead, $user)) {
            return true;
        }

        return false;
    }

    public function canWorkImmediately(Lead $lead, User $user): bool
    {
        if ($this->isReadOnly($lead, $user)) {
            return false;
        }

        if ($lead->status === LeadStatus::Holding) {
            return false;
        }

        if (! $this->compliance->hasAttemptsRemaining($lead)) {
            return false;
        }

        if (! $this->compliance->isWithinLegalWindow($lead)) {
            return false;
        }

        if ($lead->status === LeadStatus::Callback && $lead->callback_owner_id === $user->id) {
            return true;
        }

        return $lead->status === LeadStatus::Callable;
    }
}
