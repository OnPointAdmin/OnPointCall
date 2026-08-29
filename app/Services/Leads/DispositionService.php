<?php

namespace App\Services\Leads;

use App\Enums\DispositionOutcome;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Exceptions\CallbackOutsideWindowException;
use App\Exceptions\InvalidDispositionException;
use App\Exceptions\MissingDispositionReasonException;
use App\Jobs\DncPushJob;
use App\Jobs\SalesforceDncPushJob;
use App\Models\DispositionDefinition;
use App\Models\DispositionReason;
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
        string $slug,
        ?CarbonInterface $callbackAt = null,
        ?string $note = null,
        ?string $reason = null,
    ): Lead {
        $definition = DispositionDefinition::findBySlug($lead->company_id, $slug);

        if (! $definition) {
            throw InvalidDispositionException::make();
        }

        if ($definition->outcome === DispositionOutcome::Callback) {
            if (! $callbackAt || ! $this->compliance->validateCallbackTime($lead, $callbackAt)) {
                throw CallbackOutsideWindowException::make();
            }

            $callbackAt = $callbackAt->copy()->utc();
        }

        $resolvedReason = $definition->requires_reason
            ? $this->resolveReason($lead, $slug, $reason)
            : null;

        $updated = DB::transaction(function () use ($lead, $user, $definition, $slug, $callbackAt, $note, $resolvedReason): Lead {
            $lead = Lead::withoutGlobalScopes()->lockForUpdate()->findOrFail($lead->id);

            $payload = [
                'disposition' => $slug,
            ];

            if ($resolvedReason) {
                $payload['reason'] = $resolvedReason;
            }

            $trimmedNote = is_string($note) ? trim($note) : '';
            if ($trimmedNote !== '') {
                $payload['note'] = $trimmedNote;
            }

            if ($callbackAt) {
                $payload['callback_at'] = $callbackAt->toIso8601String();
            }

            $updates = [];

            if ($definition->increments_attempt) {
                $updates['attempt_count'] = $lead->attempt_count + 1;
            }

            if ($definition->increments_attempt || $definition->outcome === DispositionOutcome::Skip) {
                $updates['last_attempt_at'] = now();
            }

            match ($definition->outcome) {
                DispositionOutcome::Booked => $updates['status'] = LeadStatus::Booked,
                DispositionOutcome::Callback => $updates += [
                    'status' => LeadStatus::Callback,
                    'callback_owner_id' => $user->id,
                    'callback_at' => $callbackAt,
                ],
                DispositionOutcome::Callable, DispositionOutcome::Skip => $updates += [
                    'status' => LeadStatus::Callable,
                    'callback_owner_id' => null,
                    'callback_at' => null,
                    'next_day_part' => $this->dayPartResolver->advanceDayPart($lead),
                ],
                DispositionOutcome::Terminal => $updates += [
                    'status' => LeadStatus::Terminal,
                    'callback_owner_id' => null,
                    'callback_at' => null,
                ],
                DispositionOutcome::Dnc => $updates += [
                    'status' => LeadStatus::Dnc,
                    'callback_owner_id' => null,
                    'callback_at' => null,
                ],
            };

            if ($definition->outcome === DispositionOutcome::Skip) {
                $updates['queue_rank'] = $this->nextQueueRank($lead);
                $updates['last_skipped_by_user_id'] = $user->id;
            } else {
                $updates['last_skipped_by_user_id'] = null;
            }

            $lead->update($updates);

            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => $user->id,
                'event_type' => $definition->outcome === DispositionOutcome::Skip
                    ? LeadHistoryType::Skip
                    : LeadHistoryType::Disposition,
                'occurred_at' => now(),
                'payload' => $payload,
            ]);

            $this->claimService->releaseClaimForLead($lead, $user->id);

            return $lead->fresh(['callingList', 'claim']);
        });

        if ($definition->outcome === DispositionOutcome::Dnc) {
            DncPushJob::dispatch($updated->id);
            SalesforceDncPushJob::dispatch($updated->id, $user->id);
        }

        return $updated;
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

    private function resolveReason(Lead $lead, string $slug, ?string $reason): string
    {
        $trimmed = is_string($reason) ? trim($reason) : '';

        if ($trimmed === '') {
            throw MissingDispositionReasonException::make();
        }

        $match = DispositionReason::withoutGlobalScopes()
            ->where('company_id', $lead->company_id)
            ->where('disposition', $slug)
            ->where('active', true)
            ->where(function ($query) use ($trimmed): void {
                $query->where('label', $trimmed);

                if (ctype_digit($trimmed)) {
                    $query->orWhere('id', (int) $trimmed);
                }
            })
            ->first();

        if (! $match) {
            throw MissingDispositionReasonException::make();
        }

        return $match->label;
    }
}
