<?php

namespace App\Services\SoftScore;

use App\Enums\LeadHistoryType;
use App\Enums\SoftScoreStatus;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\LeadHistory;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SoftScoreService
{
    public function __construct(
        private readonly SoftScoreClient $client,
    ) {}

    public function shouldRun(Lead $lead): bool
    {
        return $this->shouldRunFor(
            $lead->soft_score_code,
            $lead->soft_score_checked_at,
            $lead->soft_score_status,
        );
    }

    public function shouldRunFor(
        ?string $code,
        CarbonInterface|string|null $checkedAt,
        ?SoftScoreStatus $status = null,
    ): bool {
        if ($status === SoftScoreStatus::Error) {
            return true;
        }

        if (trim((string) ($code ?? '')) === '') {
            return true;
        }

        if ($checkedAt === null || $checkedAt === '') {
            return true;
        }

        $checked = $checkedAt instanceof CarbonInterface
            ? $checkedAt
            : Carbon::parse($checkedAt);

        $days = max(0, (int) config('services.soft_score.freshness_days', 15));

        return $checked->lte(now()->subDays($days));
    }

    public function isBlank(Lead $lead): bool
    {
        return $lead->soft_score_status === null;
    }

    public function shouldShowRunButton(Lead $lead): bool
    {
        if ($lead->soft_score_status === SoftScoreStatus::Pending) {
            return false;
        }

        return $this->shouldRun($lead);
    }

    public function scoreLead(Lead $lead, ?int $actorId = null, bool $force = false): void
    {
        if (! $force && ! $this->shouldRun($lead)) {
            return;
        }

        $previousStatus = $lead->soft_score_status;
        $batchId = $lead->import_batch_id;

        DB::transaction(function () use ($lead, $previousStatus, $batchId): void {
            if ($batchId) {
                $this->moveCompletedCounterToPending($batchId, $previousStatus);
            }

            $lead->update([
                'soft_score_status' => SoftScoreStatus::Pending,
                'soft_score_last_error' => null,
            ]);
        });

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
                $this->completeBatchCounter($lead->import_batch_id, $result->status);
            }
        });
    }

    public function markRecent(Lead $lead, ?int $actorId = null): void
    {
        DB::transaction(function () use ($lead, $actorId): void {
            $lead->update([
                'soft_score_status' => SoftScoreStatus::Recent,
                'soft_score_last_error' => null,
            ]);

            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => $actorId,
                'event_type' => LeadHistoryType::SoftScore,
                'occurred_at' => now(),
                'payload' => [
                    'status' => SoftScoreStatus::Recent->value,
                    'qualification_code' => $lead->soft_score_code,
                    'skipped' => true,
                    'reason' => 'within_freshness_window',
                ],
            ]);
        });
    }

    private function moveCompletedCounterToPending(int $batchId, ?SoftScoreStatus $previous): void
    {
        if ($previous === null || $previous === SoftScoreStatus::Pending) {
            return;
        }

        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'soft_score_pending' => $batch->soft_score_pending + 1,
        ];

        match ($previous) {
            SoftScoreStatus::Complete, SoftScoreStatus::Recent => $updates['soft_score_qualified'] = max(0, $batch->soft_score_qualified - 1),
            SoftScoreStatus::Error => $updates['soft_score_error'] = max(0, $batch->soft_score_error - 1),
            SoftScoreStatus::Pending => null,
        };

        $batch->update($updates);
    }

    private function completeBatchCounter(int $batchId, SoftScoreStatus $status): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'soft_score_pending' => max(0, $batch->soft_score_pending - 1),
        ];

        match ($status) {
            SoftScoreStatus::Complete, SoftScoreStatus::Recent => $updates['soft_score_qualified'] = $batch->soft_score_qualified + 1,
            SoftScoreStatus::Error => $updates['soft_score_error'] = $batch->soft_score_error + 1,
            SoftScoreStatus::Pending => null,
        };

        $batch->update($updates);
    }
}
