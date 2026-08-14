<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\LeadHistoryType;
use App\Enums\QualificationStatus;
use App\Enums\SoftScoreStatus;
use App\Jobs\QualifyLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Import\LeadImportService;
use App\Services\Qualification\QualificationService;
use App\Services\SoftScore\SoftScoreService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QualificationCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_dispatches_qualification_jobs_when_enabled(): void
    {
        Queue::fake();

        $company = Company::factory()->create(['salesforce_id' => '001000000000001AAA']);
        CompanyContext::set($company->id);

        $csv = implode("\n", [
            'Phone,First Name,Last Name',
            '4045551111,Jane,Doe',
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
            'last_name' => 'Last Name',
        ], 'standard');

        CompanyContext::clear();

        $batch->refresh();
        $this->assertTrue($batch->run_qualification);
        $this->assertSame(1, $batch->qualification_pending);
        $this->assertSame(ImportBatchStatus::Completed, $batch->status);

        Queue::assertPushed(QualifyLeadJob::class, 1);

        $lead = Lead::withoutGlobalScopes()->where('phone', '4045551111')->first();
        $this->assertNotNull($lead);
        $this->assertSame(QualificationStatus::Pending, $lead->qualification_status);
    }

    public function test_qualification_service_maps_qualified_partners(): void
    {
        config([
            'services.qualification.client_id' => 'sf-client',
            'services.qualification.client_secret' => 'sf-secret',
            'services.qualification.instance_url' => 'https://onpointmrg.my.salesforce.com',
        ]);

        Http::fake([
            '*/services/oauth2/token' => Http::response([
                'access_token' => 'sf-token',
                'expires_in' => 3600,
            ]),
            '*/services/apexrest/CustomerQualification' => Http::response([
                'qualifiedCompaniesLead' => [
                    [
                        'companyId' => '001000000000010AAA',
                        'companyName' => 'Travel Partner',
                        'vertical' => 'Travel',
                        'priority' => '1',
                    ],
                ],
                'qualifiedCompaniesBooking' => [],
                'failedCriteria' => [],
                'errorMessage' => null,
            ]),
        ]);

        $company = Company::factory()->create(['salesforce_id' => '001000000000001AAA']);
        CompanyContext::set($company->id);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551111',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'age_range' => '35-44',
            'annual_income' => '$75,000 - $99,999',
            'zip' => '30303',
            'home_owner' => 'Own',
            'soft_score_code' => 'A',
            'status' => 'holding',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'extra_fields' => ['venueId' => 'a0X000000000001AAA'],
        ]);

        app(QualificationService::class)->qualifyLead($lead);

        CompanyContext::clear();

        $lead->refresh();
        $this->assertSame(QualificationStatus::Qualified, $lead->qualification_status);
        $this->assertNotNull($lead->qualification_checked_at);
        $this->assertNull($lead->qualification_last_error);
        $this->assertSame(['Travel Partner'], $lead->qualifiedPartnerNames());
        $this->assertSame('001000000000001AAA', $lead->qualificationRequest()['surveyCompanyId'] ?? null);
        $this->assertSame('a0X000000000001AAA', $lead->qualificationRequest()['venueId'] ?? null);
        $this->assertSame('Doe', $lead->qualificationRequest()['customerData']['lastName'] ?? null);
        $this->assertSame('A', $lead->qualificationRequest()['customerData']['qualificationCode'] ?? null);

        $this->assertDatabaseHas('lead_history', [
            'lead_id' => $lead->id,
            'event_type' => LeadHistoryType::Qualification->value,
        ]);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'CustomerQualification')) {
                return false;
            }

            $body = $request->data();

            return ($body['surveyCompanyId'] ?? null) === '001000000000001AAA'
                && ($body['venueId'] ?? null) === 'a0X000000000001AAA'
                && ($body['customerData']['lastName'] ?? null) === 'Doe'
                && ($body['customerData']['age'] ?? null) === '35-44'
                && ($body['customerData']['income'] ?? null) === '$75,000 - $99,999'
                && ($body['customerData']['zipCode'] ?? null) === '30303'
                && ($body['customerData']['qualificationCode'] ?? null) === 'A'
                && ($body['customerData']['country'] ?? null) === 'United States';
        });
    }

    public function test_qualification_service_maps_empty_lists_to_not_qualified(): void
    {
        config([
            'services.qualification.client_id' => 'sf-client',
            'services.qualification.client_secret' => 'sf-secret',
        ]);

        Http::fake([
            '*/services/oauth2/token' => Http::response(['access_token' => 'sf-token']),
            '*/services/apexrest/CustomerQualification' => Http::response([
                'qualifiedCompaniesLead' => [],
                'qualifiedCompaniesBooking' => [],
                'failedCriteria' => [
                    'Other Partner' => [
                        'combinationName' => 'Closest',
                        'failedCriteria' => ['Age Criteria'],
                    ],
                ],
                'errorMessage' => null,
            ]),
        ]);

        $company = Company::factory()->create(['salesforce_id' => '001000000000001AAA']);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045552222',
            'status' => 'holding',
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        app(QualificationService::class)->qualifyLead($lead);

        $lead->refresh();
        $this->assertSame(QualificationStatus::NotQualified, $lead->qualification_status);
        $this->assertSame([], $lead->qualifiedPartnerNames());
        $this->assertSame([
            [
                'name' => 'Other Partner',
                'combination' => 'Closest',
                'failed' => ['Age Criteria'],
            ],
        ], $lead->qualificationFailedCriteria());
    }

    public function test_qualification_errors_when_company_salesforce_id_missing(): void
    {
        $company = Company::factory()->create(['salesforce_id' => null]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045553333',
            'status' => 'holding',
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        app(QualificationService::class)->qualifyLead($lead);

        $lead->refresh();
        $this->assertSame(QualificationStatus::Error, $lead->qualification_status);
        $this->assertStringContainsString('Salesforce ID', (string) $lead->qualification_last_error);
        Http::assertNothingSent();
    }

    public function test_soft_score_job_dispatches_qualification_after_score_when_flagged(): void
    {
        Queue::fake();

        config([
            'services.soft_score.client_id' => 'ss-client',
            'services.soft_score.client_secret' => 'ss-secret',
            'services.soft_score.base_url' => 'https://softscore.test',
        ]);

        Http::fake([
            '*/oauth/v2/accesstoken*' => Http::response(['access_token' => 'ss-token', 'expires_in' => 3600]),
            '*/marketing/v1/leads/softscore' => Http::response([
                'lead' => [
                    'creditScore' => [
                        ['creditBand' => ['qualificationCode' => 'B2']],
                    ],
                ],
            ]),
        ]);

        $company = Company::factory()->create(['salesforce_id' => '001000000000001AAA']);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045554444',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'status' => 'holding',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_status' => SoftScoreStatus::Pending,
            'qualification_status' => QualificationStatus::Pending,
        ]);

        (new SoftScoreLeadJob($lead->id, null, null, true))->handle(app(SoftScoreService::class));

        $lead->refresh();
        $this->assertSame(SoftScoreStatus::Complete, $lead->soft_score_status);
        $this->assertSame('B2', $lead->soft_score_code);

        Queue::assertPushed(QualifyLeadJob::class, 1);
        Queue::assertPushed(
            QualifyLeadJob::class,
            fn (QualifyLeadJob $job): bool => $job->leadId === $lead->id,
        );
    }

    public function test_qualification_uses_refreshed_soft_score_code(): void
    {
        config([
            'services.qualification.client_id' => 'sf-client',
            'services.qualification.client_secret' => 'sf-secret',
            'services.qualification.instance_url' => 'https://onpointmrg.my.salesforce.com',
        ]);

        Http::fake([
            '*/services/oauth2/token' => Http::response(['access_token' => 'sf-token']),
            '*/services/apexrest/CustomerQualification' => Http::response([
                'qualifiedCompaniesLead' => [],
                'qualifiedCompaniesBooking' => [],
                'errorMessage' => null,
            ]),
        ]);

        $company = Company::factory()->create(['salesforce_id' => '001000000000001AAA']);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045555555',
            'last_name' => 'Doe',
            'status' => 'holding',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_code' => null,
        ]);

        // Soft Score finished after the in-memory model was loaded.
        Lead::withoutGlobalScopes()->whereKey($lead->id)->update([
            'soft_score_code' => 'C3',
            'soft_score_status' => SoftScoreStatus::Complete,
        ]);

        app(QualificationService::class)->qualifyLead($lead);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'CustomerQualification')) {
                return false;
            }

            return ($request->data()['customerData']['qualificationCode'] ?? null) === 'C3';
        });
    }
}
