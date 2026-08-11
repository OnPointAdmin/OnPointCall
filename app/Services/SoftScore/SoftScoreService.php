<?php

namespace App\Services\SoftScore;

use App\Enums\LeadHistoryType;
use App\Enums\SoftScoreStatus;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\LeadHistory;
use Illuminate\Support\Facades\DB;

class SoftScoreService
{
    public function __construct(
        private readonly SoftScoreClient $client,
    ) {}

    public function scoreLead(Lead $lead, ?int $actorId = null): void
    {
        $lead->update([
            'soft_score_status' => SoftScoreStatus::Pending,
            'soft_score_last_error' => null,
        ]);

        $result = $this->client->scoreLead($lead);

        DB::transaction(function () use ($lead, $result, $actorId): void {
            $lead->update([
                'soft_score_status' => $result->status,
                'soft_score_code' => $result->qualificationCode,
                'soft_score_checked_at' => now(),
                'soft_score_last_error' => $result->error,
            ]);

            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => $actorId,
                'event_type' => LeadHistoryType::SoftScore,
                'occurred_at' => now(),
                'payload' => [
                    'status' => $result->status->value,
                    'qualification_code' => $result->qualificationCode,
                    'error' => $result->error,
                ],
            ]);

            if ($lead->import_batch_id) {
                $this->updateBatchCounters($lead->import_batch_id, $result->status);
            }
        });
    }

    private function updateBatchCounters(int $batchId, SoftScoreStatus $status): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $pending = max(0, $batch->soft_score_pending - 1);

        $updates = ['soft_score_pending' => $pending];

        match ($status) {
            SoftScoreStatus::Qualified => $updates['soft_score_qualified'] = $batch->soft_score_qualified + 1,
            SoftScoreStatus::NotQualified => $updates['soft_score_not_qualified'] = $batch->soft_score_not_qualified + 1,
            SoftScoreStatus::Error => $updates['soft_score_error'] = $batch->soft_score_error + 1,
            SoftScoreStatus::Pending => null,
        };

        $batch->update($updates);
    }
}
