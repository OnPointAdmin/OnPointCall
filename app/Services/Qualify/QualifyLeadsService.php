<?php

namespace App\Services\Qualify;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\DncStatus;
use App\Enums\QualificationStatus;
use App\Enums\QualifyBatchStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Jobs\DncScrubJob;
use App\Jobs\QualifyLeadJob;
use App\Jobs\RndLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Models\QualifyBatch;
use App\Services\Import\HoldingReleaseService;
use Illuminate\Support\Facades\DB;

class QualifyLeadsService
{
    public function __construct(
        private readonly HoldingReleaseService $holdingReleaseService,
    ) {}

    public function queue(
        int $companyId,
        HoldingFilter $filter,
        bool $runSoftScore,
        bool $runRndCheck,
        bool $runQualification,
        bool $runDncCheck,
        ?int $maxCount,
        ?int $userId,
    ): QualifyBatch {
        $leads = $this->holdingReleaseService
            ->queryMatchingLeads($companyId, $filter, $maxCount, assignableOnly: false)
            ->orderByDesc('imported_at')
            ->get();

        $softScoreLeadIds = [];
        $qualificationLeadIds = [];
        $rndLeadIds = [];
        $dncLeadIds = [];
        $queuedLeadIds = [];

        foreach ($leads as $lead) {
            $queueSoftScore = $runSoftScore && $lead->soft_score_status !== SoftScoreStatus::Pending;
            $queueQualification = $runQualification && $lead->qualification_status !== QualificationStatus::Pending;
            $queueRnd = $runRndCheck && $lead->rnd_status !== RndStatus::Pending;
            $queueDnc = $runDncCheck && $lead->dnc_status !== DncStatus::Pending;

            if (! $queueSoftScore && ! $queueQualification && ! $queueRnd && ! $queueDnc) {
                continue;
            }

            $queuedLeadIds[] = $lead->id;

            if ($queueSoftScore) {
                $softScoreLeadIds[] = $lead->id;
            }

            if ($queueQualification) {
                $qualificationLeadIds[] = $lead->id;
            }

            if ($queueRnd) {
                $rndLeadIds[] = $lead->id;
            }

            if ($queueDnc) {
                $dncLeadIds[] = $lead->id;
            }
        }

        $hasJobs = $softScoreLeadIds !== []
            || $qualificationLeadIds !== []
            || $rndLeadIds !== []
            || $dncLeadIds !== [];

        $batch = DB::transaction(function () use (
            $companyId,
            $filter,
            $runSoftScore,
            $runRndCheck,
            $runQualification,
            $runDncCheck,
            $maxCount,
            $userId,
            $queuedLeadIds,
            $softScoreLeadIds,
            $qualificationLeadIds,
            $rndLeadIds,
            $dncLeadIds,
            $hasJobs,
        ): QualifyBatch {
            $batch = QualifyBatch::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'lead_count' => count($queuedLeadIds),
                'filter' => array_merge($filter->toArray(), ['max_count' => $maxCount]),
                'run_soft_score' => $runSoftScore,
                'run_rnd_check' => $runRndCheck,
                'run_qualification' => $runQualification,
                'run_dnc_check' => $runDncCheck,
                'soft_score_pending' => count($softScoreLeadIds),
                'rnd_pending' => count($rndLeadIds),
                'qualification_pending' => count($qualificationLeadIds),
                'dnc_pending' => count($dncLeadIds),
                'status' => $hasJobs
                    ? QualifyBatchStatus::Processing
                    : QualifyBatchStatus::Completed,
            ]);

            if ($queuedLeadIds !== []) {
                $batch->leads()->attach($queuedLeadIds);
            }

            return $batch;
        });

        $this->dispatchJobs(
            $batch->id,
            $softScoreLeadIds,
            $qualificationLeadIds,
            $rndLeadIds,
            $dncLeadIds,
            $userId,
        );

        return $batch;
    }

    /**
     * @param  list<int>  $softScoreLeadIds
     * @param  list<int>  $qualificationLeadIds
     * @param  list<int>  $rndLeadIds
     * @param  list<int>  $dncLeadIds
     */
    private function dispatchJobs(
        int $qualifyBatchId,
        array $softScoreLeadIds,
        array $qualificationLeadIds,
        array $rndLeadIds,
        array $dncLeadIds,
        ?int $actorId,
    ): void {
        $softScoreSet = array_fill_keys($softScoreLeadIds, true);
        $qualificationSet = array_fill_keys($qualificationLeadIds, true);

        foreach ($softScoreLeadIds as $leadId) {
            SoftScoreLeadJob::dispatch(
                $leadId,
                null,
                $actorId,
                isset($qualificationSet[$leadId]),
                true,
                $qualifyBatchId,
            );
        }

        foreach ($rndLeadIds as $leadId) {
            RndLeadJob::dispatch($leadId, null, $actorId, $qualifyBatchId);
        }

        foreach ($qualificationLeadIds as $leadId) {
            if (isset($softScoreSet[$leadId])) {
                continue;
            }

            QualifyLeadJob::dispatch($leadId, null, $actorId, true, $qualifyBatchId);
        }

        DncScrubJob::dispatchForLeadIds($dncLeadIds, null, $actorId, $qualifyBatchId);
    }
}
