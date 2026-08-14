<?php

namespace App\Services\Qualification;

use App\Enums\LeadHistoryType;
use App\Enums\QualificationStatus;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\LeadHistory;
use Illuminate\Support\Facades\DB;

class QualificationService
{
    public function __construct(
        private readonly QualificationClient $client,
    ) {}

    public function qualifyLead(Lead $lead, ?int $actorId = null): void
    {
        $previousStatus = $lead->qualification_status;
        $batchId = $lead->import_batch_id;

        DB::transaction(function () use ($lead, $previousStatus, $batchId): void {
            if ($batchId) {
                $this->moveCompletedCounterToPending($batchId, $previousStatus);
            }

            $lead->update([
                'qualification_status' => QualificationStatus::Pending,
                'qualification_last_error' => null,
            ]);
        });

        $result = $this->client->qualifyLead($lead);

        DB::transaction(function () use ($lead, $result, $actorId): void {
            $lead->update([
                'qualification_status' => $result->status,
                'qualification_result' => [
                    'request' => $result->request,
                    'response' => $result->payload,
                ],
                'qualification_checked_at' => now(),
                'qualification_last_error' => $result->error,
            ]);

            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => $actorId,
                'event_type' => LeadHistoryType::Qualification,
                'occurred_at' => now(),
                'payload' => [
                    'status' => $result->status->value,
                    'error' => $result->error,
                    'qualified_partners' => $lead->qualifiedPartnerNames(),
                ],
            ]);

            if ($lead->import_batch_id) {
                $this->completeBatchCounter($lead->import_batch_id, $result->status);
            }
        });
    }

    private function moveCompletedCounterToPending(int $batchId, ?QualificationStatus $previous): void
    {
        if ($previous === null || $previous === QualificationStatus::Pending) {
            return;
        }

        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'qualification_pending' => $batch->qualification_pending + 1,
        ];

        match ($previous) {
            QualificationStatus::Qualified => $updates['qualification_qualified'] = max(0, $batch->qualification_qualified - 1),
            QualificationStatus::NotQualified => $updates['qualification_not_qualified'] = max(0, $batch->qualification_not_qualified - 1),
            QualificationStatus::Error => $updates['qualification_error'] = max(0, $batch->qualification_error - 1),
            QualificationStatus::Pending => null,
        };

        $batch->update($updates);
    }

    private function completeBatchCounter(int $batchId, QualificationStatus $status): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'qualification_pending' => max(0, $batch->qualification_pending - 1),
        ];

        match ($status) {
            QualificationStatus::Qualified => $updates['qualification_qualified'] = $batch->qualification_qualified + 1,
            QualificationStatus::NotQualified => $updates['qualification_not_qualified'] = $batch->qualification_not_qualified + 1,
            QualificationStatus::Error => $updates['qualification_error'] = $batch->qualification_error + 1,
            QualificationStatus::Pending => null,
        };

        $batch->update($updates);
    }
}
