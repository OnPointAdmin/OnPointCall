<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Import\LeadImportService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_inserts_new_leads_and_ignores_duplicates_and_conflicts(): void
    {
        $company = Company::factory()->create();

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045550001',
            'external_lead_id' => 'EXISTING-A',
            'status' => LeadStatus::Holding,
            'lead_type' => LeadType::Standard,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045550002',
            'external_lead_id' => 'EXISTING-B',
            'status' => LeadStatus::Holding,
            'lead_type' => LeadType::Standard,
            'imported_at' => now(),
        ]);

        $csv = implode("\n", [
            'Phone,Lead ID,First Name',
            '4045551111,NEW-1,Jane',
            '4045550001,NEW-2,Duplicate Phone',
            '4045559999,EXISTING-A,Duplicate ID',
            '4045550002,EXISTING-A,Conflict',
        ]);

        $path = storage_path('app/imports/test-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        CompanyContext::set($company->id);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'test-import.csv', LeadType::Standard, false);

        $result = $service->process($batch, $path, [
            'phone' => 'Phone',
            'external_lead_id' => 'Lead ID',
            'first_name' => 'First Name',
        ], LeadType::Standard);

        CompanyContext::clear();

        $this->assertSame(4, $result['total_rows']);
        $this->assertSame(1, $result['inserted_count']);
        $this->assertSame(2, $result['duplicate_count']);
        $this->assertSame(1, $result['conflict_count']);
        $this->assertSame(ImportBatchStatus::Completed, $batch->fresh()->status);

        $this->assertDatabaseHas('leads', [
            'company_id' => $company->id,
            'phone' => '4045551111',
            'external_lead_id' => 'NEW-1',
            'status' => LeadStatus::Holding->value,
        ]);
    }
}
