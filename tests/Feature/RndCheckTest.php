<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\RndStatus;
use App\Jobs\RndLeadJob;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Import\LeadImportService;
use App\Services\Rnd\RndService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RndCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_dispatches_rnd_jobs_when_enabled(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $csv = implode("\n", [
            'Phone,First Name,original_lead_submit_date',
            '4045551111,Jane,2026-08-01',
        ]);

        $path = storage_path('app/imports/rnd-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'rnd-import.csv', 'standard', false, true);

        $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
            'original_lead_submit_date' => 'original_lead_submit_date',
        ], 'standard');

        CompanyContext::clear();

        $batch->refresh();
        $this->assertTrue($batch->run_rnd_check);
        $this->assertSame(1, $batch->rnd_pending);
        $this->assertSame(ImportBatchStatus::Completed, $batch->status);

        Queue::assertPushed(RndLeadJob::class, 1);

        $lead = Lead::withoutGlobalScopes()->where('phone', '4045551111')->first();
        $this->assertNotNull($lead);
        $this->assertSame(RndStatus::Pending, $lead->rnd_status);
    }

    public function test_rnd_service_checks_number_and_updates_lead(): void
    {
        config([
            'services.rnd.refresh_token' => 'test-refresh-token',
            'services.rnd.company_id' => 'TESTCO',
        ]);

        Http::fake([
            'api.reassigned.us/b/public/api/idToken' => Http::response([
                'idToken' => 'test-id-token',
            ]),
            'api.reassigned.us/api/tn' => Http::response([
                'replies' => [
                    [
                        'tn' => '4045551111',
                        'disconnected' => 'no',
                        'companyId' => 'TESTCO',
                        'dateProvided' => '2026-08-01',
                    ],
                ],
            ]),
        ]);

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551111',
            'first_name' => 'Jane',
            'status' => 'holding',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'original_lead_submit_date' => '2026-08-01',
        ]);

        app(RndService::class)->checkLead($lead);

        CompanyContext::clear();

        $lead->refresh();
        $this->assertSame(RndStatus::Clear, $lead->rnd_status);
        $this->assertNotNull($lead->rnd_checked_at);
        $this->assertNull($lead->rnd_last_error);

        $this->assertDatabaseHas('lead_history', [
            'lead_id' => $lead->id,
            'event_type' => LeadHistoryType::RndCheck->value,
        ]);
    }

    public function test_rnd_service_maps_reassigned_response(): void
    {
        config([
            'services.rnd.refresh_token' => 'test-refresh-token',
            'services.rnd.company_id' => 'TESTCO',
        ]);

        Http::fake([
            'api.reassigned.us/b/public/api/idToken' => Http::response([
                'idToken' => 'test-id-token',
            ]),
            'api.reassigned.us/api/tn' => Http::response([
                'replies' => [
                    [
                        'tn' => '4045552222',
                        'disconnected' => 'yes',
                    ],
                ],
            ]),
        ]);

        $company = Company::factory()->create();

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045552222',
            'status' => 'holding',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'original_lead_submit_date' => '2026-08-01',
        ]);

        app(RndService::class)->checkLead($lead);

        $lead->refresh();
        $this->assertSame(RndStatus::Reassigned, $lead->rnd_status);
        $this->assertSame(LeadStatus::Terminal, $lead->status);

        $this->assertDatabaseHas('lead_history', [
            'lead_id' => $lead->id,
            'event_type' => LeadHistoryType::StatusChange->value,
        ]);
    }
}
