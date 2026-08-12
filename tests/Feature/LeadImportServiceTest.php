<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Models\Company;
use App\Models\ImportMapping;
use App\Models\Lead;
use App\Services\Import\LeadImportService;
use App\Support\CompanyContext;
use Database\Seeders\ImportMappingSeeder;
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

    public function test_ssis_mapping_imports_phone_op_id_partner_list_and_extra_fields(): void
    {
        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        (new ImportMappingSeeder)->run($company->id);

        $standard = ImportMapping::query()->where('name', 'Standard Default')->first();
        $ssis = ImportMapping::query()->where('name', 'SSIS')->first();

        $this->assertNotNull($standard);
        $this->assertNotNull($ssis);
        $this->assertFalse($standard->is_default);
        $this->assertTrue($ssis->is_default);
        $this->assertSame(LeadType::Standard, $ssis->lead_type);

        $csv = implode("\n", [
            'caller_id,first_name,last_name,address,city,state,zip,email,AgeRange,annual_income,Marital Status,Gender,HomeOwner,OP_Id,jornayaleadid,trustedform,Venue,Event,original_lead_submit_date,PartnerList,File Name',
            '4045552222,Jane,Doe,1 Main St,Atlanta,GA,30301,jane@example.com,35-44,75000,Married,F,Yes,OP-1001,jornaya-ignored,tf-ignored,Kaleo,Webinar,2026-08-01,PartnerA,batch-aug.csv',
        ]);

        $path = storage_path('app/imports/ssis-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'ssis-import.csv', LeadType::Standard, false);

        $result = $service->process($batch, $path, $ssis->column_map, LeadType::Standard);

        CompanyContext::clear();

        $this->assertSame(1, $result['total_rows']);
        $this->assertSame(1, $result['inserted_count']);

        $lead = Lead::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('phone', '4045552222')
            ->first();

        $this->assertNotNull($lead);
        $this->assertSame('Jane', $lead->first_name);
        $this->assertSame('Doe', $lead->last_name);
        $this->assertSame('OP-1001', $lead->external_lead_id);
        $this->assertSame('PartnerA', $lead->partner_list);
        $this->assertSame('Kaleo', $lead->venue);
        $this->assertSame('Webinar', $lead->event);
        $this->assertSame('35-44', $lead->extra_fields['age_range'] ?? null);
        $this->assertSame('75000', $lead->extra_fields['annual_income'] ?? null);
        $this->assertSame('Married', $lead->extra_fields['marital_status'] ?? null);
        $this->assertSame('F', $lead->extra_fields['gender'] ?? null);
        $this->assertSame('Yes', $lead->extra_fields['home_owner'] ?? null);
        $this->assertSame('2026-08-01', $lead->extra_fields['original_lead_submit_date'] ?? null);
        $this->assertSame('batch-aug.csv', $lead->file_name);
        $this->assertArrayNotHasKey('file_name', $lead->extra_fields ?? []);
        $this->assertArrayNotHasKey('jornayaleadid', $lead->extra_fields ?? []);
        $this->assertArrayNotHasKey('trustedform', $lead->extra_fields ?? []);
    }
}
