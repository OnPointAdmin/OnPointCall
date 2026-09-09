<?php

namespace Tests\Feature;

use App\Enums\DncStatus;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\QualifyBatchStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Jobs\DncScrubJob;
use App\Jobs\QualifyLeadJob;
use App\Jobs\RndLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Models\Company;
use App\Models\Lead;
use App\Models\QualifyBatch;
use App\Services\Qualify\QualifyBatchCheckRetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QualifyBatchCheckRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_soft_score_errors_moves_counters_and_queues_batch_jobs(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $batch = $this->makeQualifyBatch($company->id, [
            'run_soft_score' => true,
            'soft_score_error' => 1,
            'status' => QualifyBatchStatus::Completed,
        ]);

        $errorLead = $this->makeHoldingLead($company->id, '4045559501', [
            'soft_score_status' => SoftScoreStatus::Error,
        ]);
        $clearLead = $this->makeHoldingLead($company->id, '4045559502', [
            'soft_score_status' => SoftScoreStatus::Complete,
        ]);

        $batch->leads()->attach([$errorLead->id, $clearLead->id]);

        $queued = app(QualifyBatchCheckRetryService::class)->retrySoftScoreErrors($batch, actorId: 7);

        $batch->refresh();

        $this->assertSame(1, $queued);
        $this->assertSame(1, $batch->soft_score_pending);
        $this->assertSame(0, $batch->soft_score_error);
        $this->assertSame(QualifyBatchStatus::Processing, $batch->status);
        $this->assertSame('pending', $batch->healthStatus());

        Queue::assertPushed(SoftScoreLeadJob::class, 1);
        Queue::assertPushed(
            SoftScoreLeadJob::class,
            fn (SoftScoreLeadJob $job): bool => $job->leadId === $errorLead->id
                && $job->qualifyBatchId === $batch->id
                && $job->force === true
                && $job->actorId === 7
                && $job->dispatchQualificationAfter === false,
        );
    }

    public function test_soft_score_error_retry_requeues_qualification_when_batch_ran_it(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $batch = $this->makeQualifyBatch($company->id, [
            'run_soft_score' => true,
            'run_qualification' => true,
            'soft_score_error' => 1,
            'qualification_not_qualified' => 1,
            'status' => QualifyBatchStatus::Completed,
        ]);

        $lead = $this->makeHoldingLead($company->id, '4045559503', [
            'soft_score_status' => SoftScoreStatus::Error,
            'qualification_status' => QualificationStatus::NotQualified,
        ]);
        $batch->leads()->attach($lead->id);

        $queued = app(QualifyBatchCheckRetryService::class)->retrySoftScoreErrors($batch);

        $this->assertSame(1, $queued);
        Queue::assertPushed(
            SoftScoreLeadJob::class,
            fn (SoftScoreLeadJob $job): bool => $job->leadId === $lead->id
                && $job->qualifyBatchId === $batch->id
                && $job->dispatchQualificationAfter === true,
        );
    }

    public function test_batch_retry_queues_rnd_error_jobs_for_batch_leads_only(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $batch = $this->makeQualifyBatch($company->id, [
            'run_rnd_check' => true,
            'rnd_error' => 1,
            'status' => QualifyBatchStatus::Completed,
        ]);

        $errorLead = $this->makeHoldingLead($company->id, '4045559504', [
            'rnd_status' => RndStatus::Error,
        ]);
        $outsideErrorLead = $this->makeHoldingLead($company->id, '4045559505', [
            'rnd_status' => RndStatus::Error,
        ]);

        $batch->leads()->attach($errorLead->id);

        $queued = app(QualifyBatchCheckRetryService::class)->retryRndErrors($batch);

        $batch->refresh();

        $this->assertSame(1, $queued);
        $this->assertSame(1, $batch->rnd_pending);
        $this->assertSame(0, $batch->rnd_error);
        $this->assertSame(QualifyBatchStatus::Processing, $batch->status);

        Queue::assertPushed(RndLeadJob::class, 1);
        Queue::assertPushed(
            RndLeadJob::class,
            fn (RndLeadJob $job): bool => $job->leadId === $errorLead->id
                && $job->qualifyBatchId === $batch->id,
        );
        Queue::assertNotPushed(
            RndLeadJob::class,
            fn (RndLeadJob $job): bool => $job->leadId === $outsideErrorLead->id,
        );
    }

    public function test_batch_retry_queues_qualification_error_jobs(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $batch = $this->makeQualifyBatch($company->id, [
            'run_qualification' => true,
            'qualification_error' => 1,
            'status' => QualifyBatchStatus::Completed,
        ]);

        $errorLead = $this->makeHoldingLead($company->id, '4045559506', [
            'qualification_status' => QualificationStatus::Error,
        ]);
        $qualifiedLead = $this->makeHoldingLead($company->id, '4045559507', [
            'qualification_status' => QualificationStatus::Qualified,
        ]);

        $batch->leads()->attach([$errorLead->id, $qualifiedLead->id]);

        $queued = app(QualifyBatchCheckRetryService::class)->retryQualificationErrors($batch);

        $batch->refresh();

        $this->assertSame(1, $queued);
        $this->assertSame(1, $batch->qualification_pending);
        $this->assertSame(0, $batch->qualification_error);

        Queue::assertPushed(QualifyLeadJob::class, 1);
        Queue::assertPushed(
            QualifyLeadJob::class,
            fn (QualifyLeadJob $job): bool => $job->leadId === $errorLead->id
                && $job->qualifyBatchId === $batch->id
                && $job->force === true,
        );
    }

    public function test_batch_retry_queues_dnc_error_jobs(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $batch = $this->makeQualifyBatch($company->id, [
            'run_dnc_check' => true,
            'dnc_error' => 1,
            'status' => QualifyBatchStatus::Completed,
        ]);

        $errorLead = $this->makeHoldingLead($company->id, '4045559508', [
            'dnc_status' => DncStatus::Error,
        ]);
        $clearLead = $this->makeHoldingLead($company->id, '4045559509', [
            'dnc_status' => DncStatus::Clear,
        ]);

        $batch->leads()->attach([$errorLead->id, $clearLead->id]);

        $queued = app(QualifyBatchCheckRetryService::class)->retryDncErrors($batch);

        $batch->refresh();

        $this->assertSame(1, $queued);
        $this->assertSame(1, $batch->dnc_pending);
        $this->assertSame(0, $batch->dnc_error);

        Queue::assertPushed(DncScrubJob::class, 1);
        Queue::assertPushed(
            DncScrubJob::class,
            fn (DncScrubJob $job): bool => in_array($errorLead->id, $job->leadIds, true)
                && $job->qualifyBatchId === $batch->id,
        );
    }

    public function test_retry_returns_zero_when_batch_has_no_matching_error_leads(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $batch = $this->makeQualifyBatch($company->id, [
            'run_soft_score' => true,
            'soft_score_error' => 1,
            'status' => QualifyBatchStatus::Completed,
        ]);

        $lead = $this->makeHoldingLead($company->id, '4045559510', [
            'soft_score_status' => SoftScoreStatus::Complete,
        ]);
        $batch->leads()->attach($lead->id);

        $retry = app(QualifyBatchCheckRetryService::class);

        $this->assertSame(0, $retry->retrySoftScoreErrors($batch));
        $this->assertSame(0, $retry->retryRndErrors($batch));
        $this->assertSame(0, $retry->retryQualificationErrors($batch));
        $this->assertSame(0, $retry->retryDncErrors($batch));

        Queue::assertNothingPushed();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeQualifyBatch(int $companyId, array $overrides = []): QualifyBatch
    {
        return QualifyBatch::withoutGlobalScopes()->create(array_merge([
            'company_id' => $companyId,
            'lead_count' => 0,
            'run_soft_score' => false,
            'run_rnd_check' => false,
            'run_qualification' => false,
            'run_dnc_check' => false,
            'status' => QualifyBatchStatus::Completed,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeHoldingLead(int $companyId, string $phone, array $overrides = []): Lead
    {
        return Lead::withoutGlobalScopes()->create(array_merge([
            'company_id' => $companyId,
            'phone' => $phone,
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ], $overrides));
    }
}
