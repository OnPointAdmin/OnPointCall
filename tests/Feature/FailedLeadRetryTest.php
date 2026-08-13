<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\LeadStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Jobs\RndLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Services\Import\ImportBatchCheckRetryService;
use App\Services\Import\LeadImportService;
use App\Services\SoftScore\SoftScoreService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FailedLeadRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_score_retry_moves_error_counter_to_pending_then_result(): void
    {
        config([
            'services.soft_score.client_id' => 'client',
            'services.soft_score.client_secret' => 'secret',
        ]);

        Http::fake([
            '*/oauth/v2/accesstoken*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/marketing/v1/leads/softscore' => Http::response([
                'lead' => [
                    'creditScore' => [
                        ['creditBand' => ['qualificationCode' => 'A1']],
                    ],
                ],
            ]),
        ]);

        $company = Company::factory()->create();
        $batch = ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'retry.csv',
            'imported_at' => now(),
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
            'run_soft_score' => true,
            'inserted_count' => 1,
            'soft_score_error' => 1,
            'soft_score_pending' => 0,
            'soft_score_qualified' => 0,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'import_batch_id' => $batch->id,
            'phone' => '4045557001',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_status' => SoftScoreStatus::Error,
            'soft_score_last_error' => 'credentials missing',
        ]);

        app(SoftScoreService::class)->scoreLead($lead);

        $batch->refresh();
        $lead->refresh();

        $this->assertSame(SoftScoreStatus::Complete, $lead->soft_score_status);
        $this->assertSame('A1', $lead->soft_score_code);
        $this->assertSame(0, $batch->soft_score_error);
        $this->assertSame(0, $batch->soft_score_pending);
        $this->assertSame(1, $batch->soft_score_qualified);
    }

    public function test_run_soft_score_queues_unscored_leads_and_enables_batch_flag(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $batch = ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'no-ss.csv',
            'imported_at' => now(),
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
            'run_soft_score' => false,
            'inserted_count' => 2,
            'soft_score_pending' => 0,
        ]);

        $unscored = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'import_batch_id' => $batch->id,
            'phone' => '4045557101',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_status' => null,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'import_batch_id' => $batch->id,
            'phone' => '4045557102',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A1',
        ]);

        $queued = app(ImportBatchCheckRetryService::class)->runSoftScore($batch);

        $batch->refresh();
        $unscored->refresh();

        $this->assertSame(1, $queued);
        $this->assertTrue($batch->run_soft_score);
        $this->assertSame(1, $batch->soft_score_pending);
        $this->assertSame(SoftScoreStatus::Pending, $unscored->soft_score_status);
        $this->assertSame('pending', $batch->healthStatus());

        Queue::assertPushed(SoftScoreLeadJob::class, 1);
        Queue::assertPushed(SoftScoreLeadJob::class, fn (SoftScoreLeadJob $job) => $job->leadId === $unscored->id);
    }

    public function test_batch_retry_queues_soft_score_and_rnd_error_jobs(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $batch = ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'errors.csv',
            'imported_at' => now(),
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
            'run_soft_score' => true,
            'run_rnd_check' => true,
            'soft_score_error' => 1,
            'rnd_error' => 1,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'import_batch_id' => $batch->id,
            'phone' => '4045557002',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_status' => SoftScoreStatus::Error,
            'rnd_status' => RndStatus::Clear,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'import_batch_id' => $batch->id,
            'phone' => '4045557003',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_status' => SoftScoreStatus::Complete,
            'rnd_status' => RndStatus::Error,
        ]);

        $retry = app(ImportBatchCheckRetryService::class);

        $this->assertSame(1, $retry->retrySoftScoreErrors($batch));
        $this->assertSame(1, $retry->retryRndErrors($batch));

        Queue::assertPushed(SoftScoreLeadJob::class, 1);
        Queue::assertPushed(RndLeadJob::class, 1);
    }

    public function test_reimport_updates_holding_leads_with_check_errors(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $oldBatch = ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'old.csv',
            'imported_at' => now()->subDay(),
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
            'run_soft_score' => true,
            'inserted_count' => 1,
            'soft_score_error' => 1,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'import_batch_id' => $oldBatch->id,
            'phone' => '4045557004',
            'first_name' => 'Old',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now()->subDay(),
            'soft_score_status' => SoftScoreStatus::Error,
            'soft_score_last_error' => 'credentials missing',
        ]);

        $csv = implode("\n", [
            'Phone,First Name',
            '4045557004,NewName',
        ]);
        $path = storage_path('app/imports/reimport-failed.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $newBatch = $service->createBatch($company->id, 'reimport-failed.csv', 'standard', true);
        $result = $service->process($newBatch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
        ], 'standard');

        CompanyContext::clear();

        $this->assertSame(0, $result['inserted_count']);
        $this->assertSame(1, $result['updated_count']);
        $this->assertSame(0, $result['duplicate_count']);

        $lead->refresh();
        $oldBatch->refresh();
        $newBatch->refresh();

        $this->assertSame('NewName', $lead->first_name);
        $this->assertSame($newBatch->id, $lead->import_batch_id);
        $this->assertSame(SoftScoreStatus::Pending, $lead->soft_score_status);
        $this->assertSame(0, $oldBatch->soft_score_error);
        $this->assertSame(1, $newBatch->updated_count);
        $this->assertSame(1, $newBatch->soft_score_pending);

        Queue::assertPushed(SoftScoreLeadJob::class, 1);
    }

    public function test_reimport_still_ignores_healthy_duplicates(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045557005',
            'first_name' => 'Healthy',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now()->subDay(),
            'soft_score_status' => SoftScoreStatus::Complete,
            'rnd_status' => RndStatus::Clear,
        ]);

        $csv = implode("\n", [
            'Phone,First Name',
            '4045557005,Changed',
        ]);
        $path = storage_path('app/imports/reimport-healthy.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'reimport-healthy.csv', 'standard', true, true);
        $result = $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
        ], 'standard');

        CompanyContext::clear();

        $this->assertSame(0, $result['inserted_count']);
        $this->assertSame(0, $result['updated_count']);
        $this->assertSame(1, $result['duplicate_count']);

        $lead = Lead::withoutGlobalScopes()->where('phone', '4045557005')->first();
        $this->assertSame('Healthy', $lead->first_name);
        Queue::assertNothingPushed();
    }

    public function test_reimport_ignores_terminal_rnd_reassigned_leads(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045557006',
            'status' => LeadStatus::Terminal,
            'lead_type' => 'standard',
            'imported_at' => now()->subDay(),
            'rnd_status' => RndStatus::Reassigned,
            'soft_score_status' => SoftScoreStatus::Error,
        ]);

        $csv = implode("\n", [
            'Phone,First Name',
            '4045557006,Nope',
        ]);
        $path = storage_path('app/imports/reimport-terminal.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'reimport-terminal.csv', 'standard', true);
        $result = $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
        ], 'standard');

        CompanyContext::clear();

        $this->assertSame(0, $result['updated_count']);
        $this->assertSame(1, $result['duplicate_count']);
        Queue::assertNothingPushed();
    }
}
