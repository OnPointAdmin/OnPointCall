<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\SoftScoreStatus;
use App\Jobs\QualifyLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Models\Company;
use App\Models\ImportMapping;
use App\Models\Lead;
use App\Services\Import\LeadImportService;
use App\Support\CompanyContext;
use Database\Seeders\ImportMappingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045550002',
            'external_lead_id' => 'EXISTING-B',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
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
        $batch = $service->createBatch($company->id, 'test-import.csv', 'standard', false);

        $result = $service->process($batch, $path, [
            'phone' => 'Phone',
            'external_lead_id' => 'Lead ID',
            'first_name' => 'First Name',
        ], 'standard');

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

    public function test_ssis_mapping_imports_standard_demographic_columns(): void
    {
        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        (new ImportMappingSeeder)->run($company->id);

        $ssis = ImportMapping::query()->where('name', 'SSIS')->first();

        $this->assertNotNull($ssis);
        $this->assertTrue($ssis->is_default);
        $this->assertSame('standard', $ssis->lead_type);
        $this->assertSame('AgeRange', $ssis->column_map['age_range'] ?? null);

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
        $batch = $service->createBatch($company->id, 'ssis-import.csv', 'standard', false);

        $result = $service->process($batch, $path, $ssis->column_map, 'standard');

        CompanyContext::clear();

        $this->assertSame(1, $result['inserted_count']);

        $lead = Lead::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('phone', '4045552222')
            ->first();

        $this->assertNotNull($lead);
        $this->assertSame('Jane', $lead->first_name);
        $this->assertSame('OP-1001', $lead->external_lead_id);
        $this->assertSame('PartnerA', $lead->partner_list);
        $this->assertSame('batch-aug.csv', $lead->file_name);
        $this->assertSame('35-44', $lead->age_range);
        $this->assertSame('75000', $lead->annual_income);
        $this->assertSame('Married', $lead->marital_status);
        $this->assertSame('F', $lead->gender);
        $this->assertSame('Yes', $lead->home_owner);
        $this->assertSame('2026-08-01', $lead->original_lead_submit_date);
        $this->assertTrue($lead->soft_score_checked_at?->isSameDay('2026-08-01'));
        $this->assertNull($lead->extra_fields);
    }

    public function test_tnb_mapping_imports_tour_fields_and_normalizes_phone(): void
    {
        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        (new ImportMappingSeeder)->run($company->id);

        $tnb = ImportMapping::query()->where('name', 'TNB')->first();

        $this->assertNotNull($tnb);
        $this->assertFalse($tnb->is_default);
        $this->assertSame('tnb', $tnb->lead_type);

        $csv = implode("\n", [
            'BookingId,Phone_2,FirstName,LastName,Address2,TourLocation,TourDate,Premiums,Tour_Result,TourOrNoShow',
            'BK-100,(404) 555-3333,Sam,Smith,Unit 5,Orlando Resort,2026-09-15,2 Nights,Completed,Show',
        ]);

        $path = storage_path('app/imports/tnb-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'tnb-import.csv', 'tnb', false);

        $result = $service->process($batch, $path, $tnb->column_map, 'tnb');

        CompanyContext::clear();

        $this->assertSame(1, $result['inserted_count']);

        $lead = Lead::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('phone', '4045553333')
            ->first();

        $this->assertNotNull($lead);
        $this->assertSame('tnb', $lead->lead_type);
        $this->assertSame('BK-100', $lead->booking_id);
        $this->assertSame('4045553333', $lead->phone_2);
        $this->assertSame('Sam', $lead->first_name);
        $this->assertSame('Smith', $lead->last_name);
        $this->assertSame('Unit 5', $lead->address_2);
        $this->assertSame('Orlando Resort', $lead->tour_location);
        $this->assertSame('2026-09-15', $lead->tour_date);
        $this->assertSame('2 Nights', $lead->premiums);
        $this->assertSame('Completed', $lead->tour_result);
        $this->assertSame('Show', $lead->tour_or_no_show);
    }

    public function test_import_marks_soft_score_pending_when_enabled(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $csv = implode("\n", [
            'Phone,First Name',
            '4045554444,Jane',
        ]);

        $path = storage_path('app/imports/soft-score-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'soft-score-import.csv', 'standard', true);

        $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
        ], 'standard');

        CompanyContext::clear();

        $lead = Lead::withoutGlobalScopes()->where('phone', '4045554444')->first();

        $this->assertNotNull($lead);
        $this->assertSame(SoftScoreStatus::Pending, $lead->soft_score_status);
        Queue::assertPushed(SoftScoreLeadJob::class, 1);
    }

    public function test_import_marks_qualification_pending_when_enabled(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $csv = implode("\n", [
            'Phone,First Name',
            '4045555555,Jane',
        ]);

        $path = storage_path('app/imports/qualification-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'qualification-import.csv', 'standard', false, false, true);

        $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
        ], 'standard');

        CompanyContext::clear();

        $lead = Lead::withoutGlobalScopes()->where('phone', '4045555555')->first();

        $this->assertNotNull($lead);
        $this->assertSame(QualificationStatus::Pending, $lead->qualification_status);
        Queue::assertPushed(QualifyLeadJob::class, 1);
    }
}
