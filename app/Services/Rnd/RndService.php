<?php

namespace App\Services\Rnd;

use App\DataTransferObjects\RndResult;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\RndStatus;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RndService
{
    public function __construct(
        private readonly RndClient $client,
    ) {}

    public function checkLead(Lead $lead, ?int $actorId = null): void
    {
        $previousStatus = $lead->rnd_status;
        $batchId = $lead->import_batch_id;

        DB::transaction(function () use ($lead, $previousStatus, $batchId): void {
            if ($batchId) {
                $this->moveCompletedCounterToPending($batchId, $previousStatus);
            }

            $lead->update([
                'rnd_status' => RndStatus::Pending,
                'rnd_last_error' => null,
            ]);
        });

        $consentDate = $this->resolveConsentDate($lead);
        $phone = PhoneNormalizer::normalize($lead->phone);

        if ($consentDate === null) {
            $result = new RndResult(
                status: RndStatus::Error,
                error: 'No consent date available (original submit date or import date required).',
            );
        } elseif ($phone === null) {
            $result = new RndResult(
                status: RndStatus::Error,
                error: 'Lead has no valid phone number.',
            );
        } else {
            $result = $this->client->checkNumber($phone, $consentDate);
        }

        $this->persistResult($lead, $result, $actorId);
    }

    private function persistResult(Lead $lead, RndResult $result, ?int $actorId): void
    {
        DB::transaction(function () use ($lead, $result, $actorId): void {
            $previousLeadStatus = $lead->status;

            $updates = [
                'rnd_status' => $result->status,
                'rnd_checked_at' => now(),
                'rnd_last_error' => $result->error,
            ];

            if ($result->status === RndStatus::Reassigned) {
                $updates['status'] = LeadStatus::Terminal;
            }

            $lead->update($updates);

            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => $actorId,
                'event_type' => LeadHistoryType::RndCheck,
                'occurred_at' => now(),
                'payload' => [
                    'status' => $result->status->value,
                    'error' => $result->error,
                ],
            ]);

            if ($result->status === RndStatus::Reassigned && $previousLeadStatus !== LeadStatus::Terminal) {
                LeadHistory::withoutGlobalScopes()->create([
                    'company_id' => $lead->company_id,
                    'lead_id' => $lead->id,
                    'actor_id' => $actorId,
                    'event_type' => LeadHistoryType::StatusChange,
                    'occurred_at' => now(),
                    'payload' => [
                        'from' => $previousLeadStatus->value,
                        'to' => LeadStatus::Terminal->value,
                        'reason' => 'rnd_reassigned',
                    ],
                ]);
            }

            if ($lead->import_batch_id) {
                $this->completeBatchCounter($lead->import_batch_id, $result->status);
            }
        });
    }

    private function resolveConsentDate(Lead $lead): ?string
    {
        if ($lead->original_lead_submit_date) {
            try {
                return Carbon::parse($lead->original_lead_submit_date)->format('Y-m-d');
            } catch (Throwable) {
                // fall through to imported_at
            }
        }

        if ($lead->imported_at) {
            return $lead->imported_at->format('Y-m-d');
        }

        return null;
    }

    private function moveCompletedCounterToPending(int $batchId, ?RndStatus $previous): void
    {
        if ($previous === null || $previous === RndStatus::Pending) {
            return;
        }

        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'rnd_pending' => $batch->rnd_pending + 1,
        ];

        match ($previous) {
            RndStatus::Clear => $updates['rnd_clear'] = max(0, $batch->rnd_clear - 1),
            RndStatus::Reassigned => $updates['rnd_reassigned'] = max(0, $batch->rnd_reassigned - 1),
            RndStatus::NoData => $updates['rnd_no_data'] = max(0, $batch->rnd_no_data - 1),
            RndStatus::Error => $updates['rnd_error'] = max(0, $batch->rnd_error - 1),
            RndStatus::Pending => null,
        };

        $batch->update($updates);
    }

    private function completeBatchCounter(int $batchId, RndStatus $status): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'rnd_pending' => max(0, $batch->rnd_pending - 1),
        ];

        match ($status) {
            RndStatus::Clear => $updates['rnd_clear'] = $batch->rnd_clear + 1,
            RndStatus::Reassigned => $updates['rnd_reassigned'] = $batch->rnd_reassigned + 1,
            RndStatus::NoData => $updates['rnd_no_data'] = $batch->rnd_no_data + 1,
            RndStatus::Error => $updates['rnd_error'] = $batch->rnd_error + 1,
            RndStatus::Pending => null,
        };

        $batch->update($updates);
    }
}
