<?php

namespace App\Services\Leads;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Exceptions\CallbackOutsideWindowException;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Services\Compliance\ComplianceService;
use App\Services\Compliance\DayPartResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DispositionService
{
    public function __construct(
        private readonly ComplianceService $compliance,
        private readonly DayPartResolver $dayPartResolver,
        private readonly LeadClaimService $claimService,
    ) {}

    public function apply(
        Lead $lead,
        User $user,
        Disposition $disposition,
        ?CarbonInterface $callbackAt = null,
        ?string $skipReason = null,
    ): Lead {
        if ($disposition === Disposition::Callback) {
            if (! $callbackAt || ! $this->compliance->validateCallbackTime($lead, $callbackAt)) {
                throw CallbackOutsideWindowException::make();
            }
        }

        return DB::transaction(function () use ($lead, $user, $disposition, $callbackAt, $skipReason): Lead {
            $lead = Lead::withoutGlobalScopes()->lockForUpdate()->findOrFail($lead->id);

            $payload = [
                'disposition' => $disposition->value,
            ];

            if ($skipReason) {
                $payload['skip_reason'] = $skipReason;
            }

            if ($callbackAt) {
                $payload['callback_at'] = $callbackAt->toIso8601String();
            }

            $updates = [];

            if ($disposition->incrementsAttempt()) {
                $updates['attempt_count'] = $lead->attempt_count + 1;
                $updates['last_attempt_at'] = now();
            }

            match ($disposition) {
                Disposition::Booked => $updates['status'] = LeadStatus::Booked,
                Disposition::Callback => $updates += [
                    'status' => LeadStatus::Callback,
                    'callback_owner_id' => $user->id,
                    'callback_at' => $callbackAt,
                ],
                Disposition::NoAnswer, Disposition::Voicemail => $updates += [
                    'status' => LeadStatus::Callable,
                    'callback_owner_id' => null,
                    'callback_at' => null,
                    'next_day_part' => $this->dayPartResolver->advanceDayPart($lead),
                ],
                Disposition::NotInterested, Disposition::WrongNumber, Disposition::BadLead => $updates += [
                    'status' => LeadStatus::Terminal,
                    'callback_owner_id' => null,
                    'callback_at' => null,
                ],
                Disposition::Dnc => $updates += [
                    'status' => LeadStatus::Dnc,
                    'callback_owner_id' => null,
                    'callback_at' => null,
                ],
                Disposition::Skip => $updates += [
                    'status' => LeadStatus::Callable,
                    'callback_owner_id' => null,
                    'callback_at' => null,
                    'queue_rank' => $this->nextQueueRank($lead),
                ],
            };

            $lead->update($updates);

            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => $user->id,
                'event_type' => $disposition === Disposition::Skip
                    ? LeadHistoryType::Skip
                    : LeadHistoryType::Disposition,
                'occurred_at' => now(),
                'payload' => $payload,
            ]);

            $this->claimService->releaseClaimForLead($lead, $user->id);

            return $lead->fresh(['callingList', 'claim']);
        });
    }

    private function nextQueueRank(Lead $lead): int
    {
        if (! $lead->calling_list_id) {
            return $lead->queue_rank;
        }

        $max = Lead::withoutGlobalScopes()
            ->where('company_id', $lead->company_id)
            ->where('calling_list_id', $lead->calling_list_id)
            ->max('queue_rank');

        return ((int) $max) + 1;
    }
}
