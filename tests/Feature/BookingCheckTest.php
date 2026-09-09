<?php

namespace Tests\Feature;

use App\Enums\BookingCheckStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\QualifyBatchStatus;
use App\Jobs\BookingCheckJob;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\QualifyBatch;
use App\Services\Import\HoldingReleaseService;
use App\Services\Import\ImportBatchCheckRetryService;
use App\Services\Import\LeadImportService;
use App\Services\Qualify\QualifyBatchCheckRetryService;
use App\Services\Qualify\QualifyLeadsService;
use App\Services\Salesforce\SalesforceBookingService;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_dispatches_booking_jobs_when_either_toggle_on(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $csv = implode("\n", [
            'Phone,First Name',
            '4045551111,Jane',
        ]);

        $path = storage_path('app/imports/booking-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch(
            $company->id,
            'booking-import.csv',
            'standard',
            false,
            false,
            false,
            false,
            false,
            true,
            true,
        );

        $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
        ], 'standard');

        CompanyContext::clear();

        $batch->refresh();
        $this->assertTrue($batch->exclude_future_bookings);
        $this->assertTrue($batch->exclude_past_bookings);
        $this->assertSame(1, $batch->booking_check_pending);

        Queue::assertPushed(BookingCheckJob::class, 1);
    }

    public function test_import_does_not_dispatch_booking_when_both_toggles_off(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $csv = implode("\n", [
            'Phone,First Name',
            '4045551111,Jane',
        ]);

        $path = storage_path('app/imports/booking-off-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch(
            $company->id,
            'booking-off-import.csv',
            'standard',
            false,
            false,
            false,
            false,
            false,
            false,
            false,
        );

        $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
        ], 'standard');

        CompanyContext::clear();

        $this->assertSame(0, $batch->fresh()->booking_check_pending);
        Queue::assertNotPushed(BookingCheckJob::class);
    }

    public function test_future_booking_on_phone_cleaned_marks_lead_booked(): void
    {
        $this->configureSalesforce();

        $lead = $this->makeLead('4045551212');
        $today = Carbon::parse('2026-09-09');

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                'records' => [
                    $this->bookingRecord('4045551212', 'New', '2026-09-15'),
                ],
                'done' => true,
            ]),
        ]);

        Carbon::setTestNow($today);
        app(SalesforceBookingService::class)->checkLeads(collect([$lead]));
        Carbon::setTestNow();

        $lead->refresh();
        $this->assertSame(BookingCheckStatus::FutureHit, $lead->booking_check_status);
        $this->assertSame(LeadStatus::Booked, $lead->status);
        $this->assertDatabaseHas('lead_history', [
            'lead_id' => $lead->id,
            'event_type' => LeadHistoryType::BookingCheck->value,
        ]);
    }

    public function test_future_date_with_non_future_status_is_clear(): void
    {
        $this->configureSalesforce();

        $lead = $this->makeLead('4045551212');
        $today = Carbon::parse('2026-09-09');

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                'records' => [
                    $this->bookingRecord('4045551212', 'Completed', '2026-09-15'),
                ],
                'done' => true,
            ]),
        ]);

        Carbon::setTestNow($today);
        app(SalesforceBookingService::class)->checkLeads(collect([$lead]));
        Carbon::setTestNow();

        $lead->refresh();
        $this->assertSame(BookingCheckStatus::Clear, $lead->booking_check_status);
        $this->assertSame(LeadStatus::Holding, $lead->status);
    }

    public function test_past_booking_is_ignored_when_past_toggle_off(): void
    {
        $this->configureSalesforce();

        $lead = $this->makeLeadWithBatch('4045551313', excludeFuture: true, excludePast: false);
        $today = Carbon::parse('2026-09-09');

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                'records' => [
                    $this->bookingRecord('4045551313', 'Completed', '2026-01-01'),
                ],
                'done' => true,
            ]),
        ]);

        Carbon::setTestNow($today);
        app(SalesforceBookingService::class)->checkLeads(collect([$lead]));
        Carbon::setTestNow();

        $lead->refresh();
        $this->assertSame(BookingCheckStatus::Clear, $lead->booking_check_status);
        $this->assertSame(LeadStatus::Holding, $lead->status);
    }

    public function test_past_booking_marks_lead_booked_when_toggle_on(): void
    {
        $this->configureSalesforce();

        $lead = $this->makeLeadWithBatch('4045551414', excludeFuture: false, excludePast: true);
        $today = Carbon::parse('2026-09-09');

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                'records' => [
                    $this->bookingRecord('4045551414', 'Completed', '2026-01-01'),
                ],
                'done' => true,
            ]),
        ]);

        Carbon::setTestNow($today);
        app(SalesforceBookingService::class)->checkLeads(collect([$lead]));
        Carbon::setTestNow();

        $lead->refresh();
        $this->assertSame(BookingCheckStatus::PastHit, $lead->booking_check_status);
        $this->assertSame(LeadStatus::Booked, $lead->status);
    }

    public function test_email_match_on_email_2_field(): void
    {
        $this->configureSalesforce();

        $lead = $this->makeLead('4045551515', [
            'email' => 'guest@example.com',
        ]);
        $today = Carbon::parse('2026-09-09');

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                'records' => [
                    [
                        'Id' => 'a0B0000001',
                        'Phone__c' => null,
                        'Phone_Cleaned__c' => null,
                        'Phone_2__c' => null,
                        'Email__c' => null,
                        'Email_2__c' => 'guest@example.com',
                        'Tour_Date__c' => '2026-09-20',
                        'Status__c' => 'New',
                    ],
                ],
                'done' => true,
            ]),
        ]);

        Carbon::setTestNow($today);
        app(SalesforceBookingService::class)->checkLeads(collect([$lead]));
        Carbon::setTestNow();

        $lead->refresh();
        $this->assertSame(BookingCheckStatus::FutureHit, $lead->booking_check_status);
    }

    public function test_booking_hit_reduces_valid_leads_and_blocks_assign(): void
    {
        $this->configureSalesforce();

        $company = Company::factory()->create();
        $batch = ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'booking.csv',
            'imported_at' => now(),
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
            'inserted_count' => 1,
            'exclude_future_bookings' => true,
            'exclude_past_bookings' => true,
            'booking_check_pending' => 1,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551616',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'import_batch_id' => $batch->id,
            'booking_check_status' => BookingCheckStatus::Pending,
        ]);

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                'records' => [
                    $this->bookingRecord('4045551616', 'New', '2026-09-20'),
                ],
                'done' => true,
            ]),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-09'));
        app(SalesforceBookingService::class)->checkLeads(collect([$lead->fresh()]));
        Carbon::setTestNow();

        $batch->refresh();
        $lead->refresh();

        $this->assertSame(1, $batch->booking_future_hit);
        $this->assertSame(0, $batch->booking_check_pending);
        $this->assertSame(0, $batch->valid_leads);

        $releaseService = app(HoldingReleaseService::class);
        $this->assertSame(0, $releaseService->countHolding($company->id, new \App\DataTransferObjects\HoldingFilter(leadType: 'standard')));
    }

    public function test_qualify_batch_queues_booking_jobs_with_qualify_batch_id(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551717',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'booking_check_status' => BookingCheckStatus::Clear,
        ]);

        app(QualifyLeadsService::class)->queue(
            companyId: $company->id,
            filter: new \App\DataTransferObjects\HoldingFilter(leadType: 'standard'),
            runSoftScore: false,
            runRndCheck: false,
            runQualification: false,
            runDncCheck: false,
            excludeFutureBookings: true,
            excludePastBookings: false,
            maxCount: null,
            userId: null,
        );

        Queue::assertPushed(BookingCheckJob::class, 1);
        Queue::assertPushed(
            BookingCheckJob::class,
            fn (BookingCheckJob $job): bool => $job->qualifyBatchId !== null
                && in_array($lead->id, $job->leadIds, true),
        );

        $batch = QualifyBatch::query()->first();
        $this->assertTrue($batch->exclude_future_bookings);
        $this->assertFalse($batch->exclude_past_bookings);
        $this->assertSame(1, $batch->booking_check_pending);
    }

    public function test_qualify_batch_retry_moves_booking_error_to_pending(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $batch = QualifyBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_count' => 1,
            'exclude_future_bookings' => true,
            'exclude_past_bookings' => true,
            'booking_check_error' => 1,
            'status' => QualifyBatchStatus::Completed,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551818',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'booking_check_status' => BookingCheckStatus::Error,
        ]);

        $batch->leads()->attach($lead->id);

        app(QualifyBatchCheckRetryService::class)->retryBookingErrors($batch);

        $batch->refresh();
        $this->assertSame(1, $batch->booking_check_pending);
        $this->assertSame(0, $batch->booking_check_error);
        Queue::assertPushed(BookingCheckJob::class, 1);
    }

    public function test_import_batch_retry_booking_errors(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $batch = ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'booking-retry.csv',
            'imported_at' => now(),
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
            'exclude_future_bookings' => true,
            'exclude_past_bookings' => true,
            'booking_check_error' => 1,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551919',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'import_batch_id' => $batch->id,
            'booking_check_status' => BookingCheckStatus::Error,
        ]);

        app(ImportBatchCheckRetryService::class)->retryBookingErrors($batch);

        Queue::assertPushed(BookingCheckJob::class, 1);
    }

    private function configureSalesforce(): void
    {
        config([
            'services.qualification.client_id' => 'client',
            'services.qualification.client_secret' => 'secret',
            'services.qualification.instance_url' => 'https://example.salesforce.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLead(string $phone, array $overrides = []): Lead
    {
        $company = Company::factory()->create();

        return Lead::withoutGlobalScopes()->create(array_merge([
            'company_id' => $company->id,
            'phone' => $phone,
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'booking_check_status' => BookingCheckStatus::Pending,
        ], $overrides));
    }

    private function makeLeadWithBatch(string $phone, bool $excludeFuture, bool $excludePast): Lead
    {
        $company = Company::factory()->create();

        $batch = ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'booking.csv',
            'imported_at' => now(),
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
            'exclude_future_bookings' => $excludeFuture,
            'exclude_past_bookings' => $excludePast,
        ]);

        return $this->makeLead($phone, [
            'company_id' => $company->id,
            'import_batch_id' => $batch->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingRecord(string $phoneCleaned, string $status, string $tourDate): array
    {
        return [
            'Id' => 'a0B'.substr(md5($phoneCleaned), 0, 12),
            'Phone__c' => $phoneCleaned,
            'Phone_Cleaned__c' => $phoneCleaned,
            'Phone_2__c' => null,
            'Email__c' => null,
            'Email_2__c' => null,
            'Tour_Date__c' => $tourDate,
            'Status__c' => $status,
        ];
    }
}
