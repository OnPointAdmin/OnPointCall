<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\QualifyBatchStatus;
use App\Enums\SoftScoreStatus;
use App\Enums\UserRole;
use App\Filament\Resources\QualifyBatches\Pages\ViewQualifyBatch;
use App\Filament\Resources\QualifyBatches\RelationManagers\LeadsRelationManager;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\QualifyBatch;
use App\Models\User;
use App\Services\SoftScore\SoftScoreService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class QualifyBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_page_groups_batch_details_into_sections(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
        ]);
        CompanyContext::set($company->id);

        $batch = QualifyBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'lead_count' => 1,
            'run_soft_score' => true,
            'run_dnc_check' => true,
            'status' => QualifyBatchStatus::Processing,
        ]);

        Livewire::actingAs($admin)
            ->test(ViewQualifyBatch::class, ['record' => $batch->getRouteKey()])
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('Run summary')
            ->assertSee('Checks run')
            ->assertSee('Soft Score')
            ->assertSee('DNC')
            ->assertSee('Internal DNC')
            ->assertSee('Leads');
    }

    public function test_leads_relation_manager_loads_without_ambiguous_id_sort(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
        ]);
        CompanyContext::set($company->id);

        $batch = QualifyBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'lead_count' => 1,
            'run_soft_score' => true,
            'status' => QualifyBatchStatus::Processing,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559010',
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $batch->leads()->attach($lead->id);

        Livewire::actingAs($admin)
            ->test(LeadsRelationManager::class, [
                'ownerRecord' => $batch,
                'pageClass' => ViewQualifyBatch::class,
            ])
            ->assertOk()
            ->assertCanSeeTableRecords([$lead]);
    }

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

    public function test_nq_code_increments_not_qualified_and_q_pc1_increment_qualified(): void
    {
        config([
            'services.soft_score.client_id' => 'client',
            'services.soft_score.client_secret' => 'secret',
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'accesstoken')) {
                return Http::response(['access_token' => 'token', 'expires_in' => 3600]);
            }

            $phone = data_get($request->data(), 'leadRequest.homePhone');
            $code = match ($phone) {
                '4045559101' => 'Q',
                '4045559102' => 'PC1',
                default => 'NQ',
            };

            return Http::response([
                'lead' => [
                    'creditScore' => [
                        ['creditBand' => ['qualificationCode' => $code]],
                    ],
                ],
            ]);
        });

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $batch = QualifyBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_count' => 3,
            'run_soft_score' => true,
            'soft_score_pending' => 3,
            'status' => QualifyBatchStatus::Processing,
        ]);

        $qualified = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559101',
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);
        $pc1 = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559102',
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);
        $notQualified = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559103',
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $batch->leads()->attach([$qualified->id, $pc1->id, $notQualified->id]);

        $service = app(SoftScoreService::class);
        $service->scoreLead($qualified, null, force: true, qualifyBatchId: $batch->id);
        $service->scoreLead($pc1, null, force: true, qualifyBatchId: $batch->id);
        $service->scoreLead($notQualified, null, force: true, qualifyBatchId: $batch->id);

        $batch->refresh();

        $this->assertSame('Q', $qualified->fresh()->soft_score_code);
        $this->assertSame('PC1', $pc1->fresh()->soft_score_code);
        $this->assertSame('NQ', $notQualified->fresh()->soft_score_code);
        $this->assertSame(0, $batch->soft_score_pending);
        $this->assertSame(2, $batch->soft_score_qualified);
        $this->assertSame(1, $batch->soft_score_not_qualified);
        $this->assertSame(0, $batch->soft_score_error);
    }

    public function test_leads_table_filters_by_soft_score_code(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
        ]);
        CompanyContext::set($company->id);

        $batch = QualifyBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'lead_count' => 3,
            'run_soft_score' => true,
            'status' => QualifyBatchStatus::Completed,
        ]);

        $q = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559201',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'Q',
        ]);
        $nq = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559202',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'NQ',
        ]);
        $pc1 = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559203',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'PC1',
        ]);

        $batch->leads()->attach([$q->id, $nq->id, $pc1->id]);

        Livewire::actingAs($admin)
            ->test(LeadsRelationManager::class, [
                'ownerRecord' => $batch,
                'pageClass' => ViewQualifyBatch::class,
            ])
            ->assertCanSeeTableRecords([$q, $nq, $pc1])
            ->filterTable('soft_score_code', 'NQ')
            ->assertCanSeeTableRecords([$nq])
            ->assertCanNotSeeTableRecords([$q, $pc1]);
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

        $importBatch = ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'import.csv',
            'imported_at' => now(),
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
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
