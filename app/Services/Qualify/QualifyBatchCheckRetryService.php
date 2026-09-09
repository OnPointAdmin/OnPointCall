<?php

namespace App\Services\Qualify;

use App\Enums\DncStatus;
use App\Enums\QualificationStatus;
use App\Enums\QualifyBatchStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Jobs\DncScrubJob;
use App\Jobs\QualifyLeadJob;
use App\Jobs\RndLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Models\Lead;
use App\Models\QualifyBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QualifyBatchCheckRetryService
{
    public function retrySoftScoreErrors(QualifyBatch $batch, ?int $actorId = null): int
    {
        $leads = $this->errorLeads($batch, 'soft_score_status', SoftScoreStatus::Error);

        if ($leads->isEmpty()) {
            return 0;
        }

        $this->moveErrorToPending($batch, 'soft_score_error', 'soft_score_pending', $leads->count());

        foreach ($leads as $lead) {
            SoftScoreLeadJob::dispatch(
                $lead->id,
                $lead->import_batch_id,
                $actorId,
                $batch->run_qualification
                    || $lead->qualification_status === QualificationStatus::Pending,
                true,
                $batch->id,
            );
        }

        return $leads->count();
    }

    public function retryRndErrors(QualifyBatch $batch, ?int $actorId = null): int
    {
        $leads = $this->errorLeads($batch, 'rnd_status', RndStatus::Error);

        if ($leads->isEmpty()) {
            return 0;
        }

        $this->moveErrorToPending($batch, 'rnd_error', 'rnd_pending', $leads->count());

        foreach ($leads as $lead) {
            RndLeadJob::dispatch($lead->id, $lead->import_batch_id, $actorId, $batch->id);
        }

        return $leads->count();
    }

    public function retryQualificationErrors(QualifyBatch $batch, ?int $actorId = null): int
    {
        $leads = $this->errorLeads($batch, 'qualification_status', QualificationStatus::Error);

        if ($leads->isEmpty()) {
            return 0;
        }

        $this->moveErrorToPending($batch, 'qualification_error', 'qualification_pending', $leads->count());

        foreach ($leads as $lead) {
            QualifyLeadJob::dispatch($lead->id, $lead->import_batch_id, $actorId, true, $batch->id);
        }

        return $leads->count();
    }

    public function retryDncErrors(QualifyBatch $batch, ?int $actorId = null): int
    {
        $leads = $this->errorLeads($batch, 'dnc_status', DncStatus::Error);

        if ($leads->isEmpty()) {
            return 0;
        }

        $this->moveErrorToPending($batch, 'dnc_error', 'dnc_pending', $leads->count());

        DncScrubJob::dispatchForLeadIds(
            $leads->pluck('id')->all(),
            null,
            $actorId,
            $batch->id,
        );

        return $leads->count();
    }

    /**
     * @return Collection<int, Lead>
     */
    private function errorLeads(QualifyBatch $batch, string $column, mixed $status): Collection
    {
        return Lead::withoutGlobalScopes()
            ->whereIn('id', $batch->leads()->select('leads.id'))
            ->where($column, $status)
            ->get();
    }

    private function moveErrorToPending(
        QualifyBatch $batch,
        string $errorColumn,
        string $pendingColumn,
        int $count,
    ): void {
        DB::transaction(function () use ($batch, $errorColumn, $pendingColumn, $count): void {
            $locked = QualifyBatch::withoutGlobalScopes()->lockForUpdate()->findOrFail($batch->id);

            $locked->update([
                $pendingColumn => $locked->{$pendingColumn} + $count,
                $errorColumn => max(0, $locked->{$errorColumn} - $count),
                'status' => QualifyBatchStatus::Processing,
            ]);
        });
    }
}
