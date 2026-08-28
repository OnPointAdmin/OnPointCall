<?php

namespace App\Services\Import;

use App\Enums\DncStatus;
use App\Enums\QualificationStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Jobs\DncScrubJob;
use App\Jobs\QualifyLeadJob;
use App\Jobs\RndLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Models\ImportBatch;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class ImportBatchCheckRetryService
{
    public function runSoftScore(ImportBatch $batch, ?int $actorId = null): int
    {
        $leads = Lead::withoutGlobalScopes()
            ->where('import_batch_id', $batch->id)
            ->whereNull('soft_score_status')
            ->get();

        if ($leads->isEmpty()) {
            if (! $batch->run_soft_score) {
                $batch->update(['run_soft_score' => true]);
            }

            return 0;
        }

        DB::transaction(function () use ($batch, $leads): void {
            $locked = ImportBatch::withoutGlobalScopes()->lockForUpdate()->findOrFail($batch->id);

            $locked->update([
                'run_soft_score' => true,
                'soft_score_pending' => $locked->soft_score_pending + $leads->count(),
            ]);

            Lead::withoutGlobalScopes()
                ->whereIn('id', $leads->pluck('id'))
                ->update(['soft_score_status' => SoftScoreStatus::Pending]);
        });

        foreach ($leads as $lead) {
            SoftScoreLeadJob::dispatch(
                $lead->id,
                $batch->id,
                $actorId,
                $lead->qualification_status === QualificationStatus::Pending,
            );
        }

        return $leads->count();
    }

    public function runQualification(ImportBatch $batch, ?int $actorId = null): int
    {
        $leads = Lead::withoutGlobalScopes()
            ->where('import_batch_id', $batch->id)
            ->whereNull('qualification_status')
            ->get();

        if ($leads->isEmpty()) {
            if (! $batch->run_qualification) {
                $batch->update(['run_qualification' => true]);
            }

            return 0;
        }

        DB::transaction(function () use ($batch, $leads): void {
            $locked = ImportBatch::withoutGlobalScopes()->lockForUpdate()->findOrFail($batch->id);

            $locked->update([
                'run_qualification' => true,
                'qualification_pending' => $locked->qualification_pending + $leads->count(),
            ]);

            Lead::withoutGlobalScopes()
                ->whereIn('id', $leads->pluck('id'))
                ->update(['qualification_status' => QualificationStatus::Pending]);
        });

        foreach ($leads as $lead) {
            // If Soft Score is still Pending, QualifyLeadJob releases until soft_score_code is saved.
            QualifyLeadJob::dispatch($lead->id, $batch->id, $actorId);
        }

        return $leads->count();
    }

    public function runDncCheck(ImportBatch $batch, ?int $actorId = null): int
    {
        $leads = Lead::withoutGlobalScopes()
            ->where('import_batch_id', $batch->id)
            ->whereNull('dnc_status')
            ->get();

        if ($leads->isEmpty()) {
            if (! $batch->run_dnc_check) {
                $batch->update(['run_dnc_check' => true]);
            }

            return 0;
        }

        DB::transaction(function () use ($batch, $leads): void {
            $locked = ImportBatch::withoutGlobalScopes()->lockForUpdate()->findOrFail($batch->id);

            $locked->update([
                'run_dnc_check' => true,
                'dnc_pending' => $locked->dnc_pending + $leads->count(),
            ]);

            Lead::withoutGlobalScopes()
                ->whereIn('id', $leads->pluck('id'))
                ->update(['dnc_status' => DncStatus::Pending]);
        });

        DncScrubJob::dispatchForLeadIds($leads->pluck('id')->all(), $batch->id, $actorId);

        return $leads->count();
    }

    public function retrySoftScoreErrors(ImportBatch $batch, ?int $actorId = null): int
    {
        $leads = Lead::withoutGlobalScopes()
            ->where('import_batch_id', $batch->id)
            ->where('soft_score_status', SoftScoreStatus::Error)
            ->get();

        foreach ($leads as $lead) {
            SoftScoreLeadJob::dispatch(
                $lead->id,
                $batch->id,
                $actorId,
                $batch->run_qualification
                    || $lead->qualification_status === QualificationStatus::Pending,
            );
        }

        return $leads->count();
    }

    public function retryRndErrors(ImportBatch $batch, ?int $actorId = null): int
    {
        $leads = Lead::withoutGlobalScopes()
            ->where('import_batch_id', $batch->id)
            ->where('rnd_status', RndStatus::Error)
            ->get();

        foreach ($leads as $lead) {
            RndLeadJob::dispatch($lead->id, $batch->id, $actorId);
        }

        return $leads->count();
    }

    public function retryQualificationErrors(ImportBatch $batch, ?int $actorId = null): int
    {
        $leads = Lead::withoutGlobalScopes()
            ->where('import_batch_id', $batch->id)
            ->where('qualification_status', QualificationStatus::Error)
            ->get();

        foreach ($leads as $lead) {
            QualifyLeadJob::dispatch($lead->id, $batch->id, $actorId);
        }

        return $leads->count();
    }

    public function retryDncErrors(ImportBatch $batch, ?int $actorId = null): int
    {
        $leads = Lead::withoutGlobalScopes()
            ->where('import_batch_id', $batch->id)
            ->where('dnc_status', DncStatus::Error)
            ->get();

        DncScrubJob::dispatchForLeadIds($leads->pluck('id')->all(), $batch->id, $actorId);

        return $leads->count();
    }
}
