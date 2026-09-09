<?php

namespace Tests\Feature;

use App\Enums\QualifyBatchStatus;
use App\Enums\SoftScoreStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\QualifyBatch;
use App\Models\User;
use App\Services\SoftScore\SoftScoreService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QualifyBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_is_pending_while_checks_remain_then_ok_after_complete(): void
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
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
        ]);
        CompanyContext::set($company->id);

        $batch = QualifyBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'lead_count' => 1,
            'run_soft_score' => true,
            'soft_score_pending' => 1,
            'status' => QualifyBatchStatus::Processing,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559001',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'B2',
        ]);

        $batch->leads()->attach($lead->id);

        $this->assertSame('pending', $batch->healthStatus());
        $this->assertSame(QualifyBatchStatus::Processing, $batch->status);

        app(SoftScoreService::class)->scoreLead($lead, $user->id, force: true, qualifyBatchId: $batch->id);

        $batch->refresh();
        $lead->refresh();

        $this->assertSame(SoftScoreStatus::Complete, $lead->soft_score_status);
        $this->assertSame('A1', $lead->soft_score_code);
        $this->assertSame(0, $batch->soft_score_pending);
        $this->assertSame(1, $batch->soft_score_qualified);
        $this->assertSame(0, $batch->soft_score_error);
        $this->assertSame(QualifyBatchStatus::Completed, $batch->status);
        $this->assertSame('ok', $batch->healthStatus());
    }

    public function test_health_is_error_when_soft_score_errors_exist(): void
    {
        $company = Company::factory()->create();

        $batch = QualifyBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_count' => 1,
            'run_soft_score' => true,
            'soft_score_error' => 1,
            'status' => QualifyBatchStatus::Completed,
        ]);

        $this->assertSame('error', $batch->healthStatus());
        $this->assertSame('Errors', $batch->healthLabel());
    }

    public function test_import_batch_counters_are_unchanged_when_qualify_batch_completes(): void
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
        CompanyContext::set($company->id);

        $importBatch = \App\Models\ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'import.csv',
            'imported_at' => now(),
            'lead_type' => 'standard',
            'status' => \App\Enums\ImportBatchStatus::Completed,
            'run_soft_score' => true,
            'soft_score_qualified' => 1,
            'soft_score_pending' => 0,
        ]);

        $qualifyBatch = QualifyBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_count' => 1,
            'run_soft_score' => true,
            'soft_score_pending' => 1,
            'status' => QualifyBatchStatus::Processing,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'import_batch_id' => $importBatch->id,
            'phone' => '4045559002',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'B2',
        ]);

        app(SoftScoreService::class)->scoreLead($lead, null, force: true, qualifyBatchId: $qualifyBatch->id);

        $importBatch->refresh();
        $qualifyBatch->refresh();

        $this->assertSame(0, $importBatch->soft_score_pending);
        $this->assertSame(1, $importBatch->soft_score_qualified);
        $this->assertSame(0, $qualifyBatch->soft_score_pending);
        $this->assertSame(1, $qualifyBatch->soft_score_qualified);
    }
}
