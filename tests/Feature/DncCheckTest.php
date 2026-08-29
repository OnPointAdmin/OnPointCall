<?php

namespace Tests\Feature;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\Disposition;
use App\Enums\DncStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Jobs\DncPushJob;
use App\Jobs\DncScrubJob;
use App\Jobs\SalesforceDncPushJob;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Services\Dnc\DncService;
use App\Services\Import\HoldingReleaseService;
use App\Services\Import\ImportBatchCheckRetryService;
use App\Services\Import\LeadImportService;
use App\Services\Leads\DispositionService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class DncCheckTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_import_dispatches_batched_dnc_jobs_when_enabled(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $csv = implode("\n", [
            'Phone,First Name',
            '4045551111,Jane',
            '4045552222,John',
        ]);

        $path = storage_path('app/imports/dnc-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'dnc-import.csv', 'standard', false, false, false, true, true);

        $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
        ], 'standard');

        CompanyContext::clear();

        $batch->refresh();
        $this->assertTrue($batch->run_dnc_check);
        $this->assertTrue($batch->ignore_national_dnc);
        $this->assertSame(2, $batch->dnc_pending);
        $this->assertSame(ImportBatchStatus::Completed, $batch->status);

        Queue::assertPushed(DncScrubJob::class, 1);
        Queue::assertPushed(
            DncScrubJob::class,
            fn (DncScrubJob $job): bool => count($job->leadIds) === 2,
        );

        $lead = Lead::withoutGlobalScopes()->where('phone', '4045551111')->first();
        $this->assertNotNull($lead);
        $this->assertSame(DncStatus::Pending, $lead->dnc_status);
    }

    public function test_import_does_not_dispatch_dnc_when_disabled(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $csv = implode("\n", [
            'Phone,First Name',
            '4045551111,Jane',
        ]);

        $path = storage_path('app/imports/dnc-off-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'dnc-off-import.csv', 'standard', false);

        $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
        ], 'standard');

        CompanyContext::clear();

        $this->assertFalse($batch->fresh()->run_dnc_check);
        $this->assertFalse($batch->fresh()->ignore_national_dnc);
        Queue::assertNotPushed(DncScrubJob::class);
    }

    public function test_national_dnc_marks_lead_dnc(): void
    {
        $this->configureDnc();

        $lead = $this->makeLead('4045551111');

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('4045551111', $lead->id.':phone', 'D', 'National (USA) 2003-06-01;;;'),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$lead]));

        $lead->refresh();
        $this->assertSame(DncStatus::Hit, $lead->dnc_status);
        $this->assertSame(LeadStatus::Dnc, $lead->status);
        $this->assertSame('national', $lead->dnc_result['hit_reason']);
        $this->assertDatabaseHas('lead_history', [
            'lead_id' => $lead->id,
            'event_type' => LeadHistoryType::DncCheck->value,
        ]);
        $this->assertDatabaseHas('lead_history', [
            'lead_id' => $lead->id,
            'event_type' => LeadHistoryType::StatusChange->value,
        ]);
    }

    public function test_litigator_marks_lead_dnc(): void
    {
        $this->configureDnc();

        $lead = $this->makeLead('5039367187');

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('5039367187', $lead->id.':phone', 'D', 'Litigator'),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$lead]));

        $lead->refresh();
        $this->assertSame(DncStatus::Hit, $lead->dnc_status);
        $this->assertSame(LeadStatus::Dnc, $lead->status);
        $this->assertSame('litigator', $lead->dnc_result['hit_reason']);
    }

    public function test_internal_dnc_marks_lead_dnc(): void
    {
        $this->configureDnc();

        $lead = $this->makeLead('4045553333');

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('4045553333', $lead->id.':phone', 'P', ''),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$lead]));

        $lead->refresh();
        $this->assertSame(DncStatus::Hit, $lead->dnc_status);
        $this->assertSame(LeadStatus::Dnc, $lead->status);
        $this->assertSame('idnc', $lead->dnc_result['hit_reason']);
    }

    public function test_invalid_area_code_marks_lead_terminal(): void
    {
        $this->configureDnc();

        $lead = $this->makeLead('4045554444');

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('4045554444', $lead->id.':phone', 'I', ''),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$lead]));

        $lead->refresh();
        $this->assertSame(DncStatus::Invalid, $lead->dnc_status);
        $this->assertSame(LeadStatus::Terminal, $lead->status);
        $this->assertSame('invalid', $lead->dnc_result['hit_reason']);
    }

    public function test_wireless_without_dnc_is_clear(): void
    {
        $this->configureDnc();

        $lead = $this->makeLead('4045555555');

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('4045555555', $lead->id.':phone', 'W', ';;;W'),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$lead]));

        $lead->refresh();
        $this->assertSame(DncStatus::Clear, $lead->dnc_status);
        $this->assertSame(LeadStatus::Holding, $lead->status);
    }

    public function test_state_dnc_marks_lead_dnc(): void
    {
        $this->configureDnc();

        $lead = $this->makeLead('4045551112');

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('4045551112', $lead->id.':phone', 'D', ';Florida;;'),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$lead]));

        $lead->refresh();
        $this->assertSame(DncStatus::Hit, $lead->dnc_status);
        $this->assertSame(LeadStatus::Dnc, $lead->status);
        $this->assertSame('state', $lead->dnc_result['hit_reason']);
        $this->assertContains('state', $lead->dnc_result['phones']['phone']['flags']);
    }

    public function test_national_and_state_hit_prefers_state_reason(): void
    {
        $this->configureDnc();

        $lead = $this->makeLead('4045551113');

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('4045551113', $lead->id.':phone', 'D', 'National (USA) 2003-06-01;California;;'),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$lead]));

        $lead->refresh();
        $this->assertSame(DncStatus::Hit, $lead->dnc_status);
        $this->assertSame('state', $lead->dnc_result['hit_reason']);
        $this->assertEqualsCanonicalizing(['national', 'state'], $lead->dnc_result['phones']['phone']['flags']);
    }

    public function test_dnc_check_history_and_lead_show_formatted_details(): void
    {
        $this->configureDnc();

        $lead = $this->makeLead('4045551113');

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('4045551113', $lead->id.':phone', 'D', 'National (USA) 2023-10-20;State (FL) 2023-12-06;;'),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$lead]));

        $lead->refresh();
        $this->assertSame(
            'National DNC since 10-20-2023 State DNC since 12-06-2023',
            $lead->dncDetailLabel(),
        );

        $history = LeadHistory::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('event_type', LeadHistoryType::DncCheck)
            ->first();

        $this->assertNotNull($history);
        $this->assertStringContainsString(
            'DNC · National DNC since 10-20-2023 State DNC since 12-06-2023',
            $history->detailLabel(),
        );
    }

    public function test_ignored_national_dnc_leaves_lead_clear(): void
    {
        $this->configureDnc();

        $lead = $this->makeLeadWithBatch('4045551114', ignoreNationalDnc: true);

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('4045551114', $lead->id.':phone', 'D', 'National (USA) 2003-06-01;;;'),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$lead]));

        $lead->refresh();
        $this->assertSame(DncStatus::Clear, $lead->dnc_status);
        $this->assertSame(LeadStatus::Holding, $lead->status);
        $this->assertNull($lead->dnc_result['hit_reason']);
        $this->assertTrue($lead->dnc_result['ignore_national_dnc']);
        $this->assertSame(['national'], $lead->dnc_result['ignored_reasons']);
        $this->assertSame('national', $lead->dnc_result['phones']['phone']['suppress']);
        $this->assertSame(
            'National DNC since 06-01-2003 (ignored — consent)',
            $lead->dncDetailLabel(),
        );
        $this->assertDatabaseMissing('lead_history', [
            'lead_id' => $lead->id,
            'event_type' => LeadHistoryType::StatusChange->value,
        ]);
    }

    public function test_consent_ignores_state_dnc_as_well_as_national(): void
    {
        $this->configureDnc();

        $lead = $this->makeLeadWithBatch('4045551115', ignoreNationalDnc: true);

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('4045551115', $lead->id.':phone', 'D', 'National (USA) 2003-06-01;State (FL) 2003-12-10;;'),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$lead]));

        $lead->refresh();
        $this->assertSame(DncStatus::Clear, $lead->dnc_status);
        $this->assertSame(LeadStatus::Holding, $lead->status);
        $this->assertNull($lead->dnc_result['hit_reason']);
        $this->assertEqualsCanonicalizing(['national', 'state'], $lead->dnc_result['ignored_reasons']);
        $this->assertEqualsCanonicalizing(['national', 'state'], $lead->dnc_result['phones']['phone']['flags']);
    }

    public function test_ignored_national_still_flags_litigator_and_internal_dnc(): void
    {
        $this->configureDnc();

        $litigator = $this->makeLeadWithBatch('5039367187', ignoreNationalDnc: true);
        $internal = $this->makeLeadWithBatch('4045553333', ignoreNationalDnc: true);

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('5039367187', $litigator->id.':phone', 'D', 'Litigator'),
                $this->scrubRow('4045553333', $internal->id.':phone', 'P', ''),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$litigator, $internal]));

        $litigator->refresh();
        $internal->refresh();
        $this->assertSame(DncStatus::Hit, $litigator->dnc_status);
        $this->assertSame('litigator', $litigator->dnc_result['hit_reason']);
        $this->assertSame(DncStatus::Hit, $internal->dnc_status);
        $this->assertSame('idnc', $internal->dnc_result['hit_reason']);
    }

    public function test_batch_without_ignore_still_flags_national_dnc(): void
    {
        $this->configureDnc();

        $lead = $this->makeLeadWithBatch('4045551116', ignoreNationalDnc: false);

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('4045551116', $lead->id.':phone', 'D', 'National (USA) 2003-06-01;;;'),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$lead]));

        $lead->refresh();
        $this->assertSame(DncStatus::Hit, $lead->dnc_status);
        $this->assertSame(LeadStatus::Dnc, $lead->status);
        $this->assertSame('national', $lead->dnc_result['hit_reason']);
        $this->assertFalse($lead->dnc_result['ignore_national_dnc']);
        $this->assertSame([], $lead->dnc_result['ignored_reasons']);
    }

    public function test_reapply_releases_national_only_stored_hit(): void
    {
        $lead = $this->makeStoredHitLead('4045552001', 'National (USA) 2026-08-22;;;');
        $batch = ImportBatch::withoutGlobalScopes()->findOrFail($lead->import_batch_id);
        $batch->update(['dnc_hit' => 1, 'dnc_clear' => 0, 'ignore_national_dnc' => false]);

        Http::fake();

        $result = app(ImportBatchCheckRetryService::class)->reapplyDncConsentPolicy($batch);

        $lead->refresh();
        $batch->refresh();
        $this->assertSame(1, $result['released']);
        $this->assertSame(0, $result['remaining_hits']);
        $this->assertSame(DncStatus::Clear, $lead->dnc_status);
        $this->assertSame(LeadStatus::Holding, $lead->status);
        $this->assertTrue($lead->dnc_result['ignore_national_dnc']);
        $this->assertSame(['national'], $lead->dnc_result['ignored_reasons']);
        $this->assertTrue($batch->ignore_national_dnc);
        $this->assertSame(0, $batch->dnc_hit);
        $this->assertSame(1, $batch->dnc_clear);
        Http::assertNothingSent();
    }

    public function test_reapply_releases_florida_state_hit_when_consent_applies(): void
    {
        $lead = $this->makeStoredHitLead('4045552002', 'National (USA) 2003-06-01;State (FL) 2003-12-10;;');
        $batch = ImportBatch::withoutGlobalScopes()->findOrFail($lead->import_batch_id);
        $batch->update(['dnc_hit' => 1, 'dnc_clear' => 0]);

        $result = app(ImportBatchCheckRetryService::class)->reapplyDncConsentPolicy($batch);

        $lead->refresh();
        $this->assertSame(1, $result['released']);
        $this->assertSame(0, $result['remaining_hits']);
        $this->assertSame(DncStatus::Clear, $lead->dnc_status);
        $this->assertSame(LeadStatus::Holding, $lead->status);
        $this->assertNull($lead->dnc_result['hit_reason']);
        $this->assertEqualsCanonicalizing(['national', 'state'], $lead->dnc_result['ignored_reasons']);
        $this->assertEqualsCanonicalizing(['national', 'state'], $lead->dnc_result['phones']['phone']['flags']);
    }

    public function test_reapply_skips_agent_marked_dnc(): void
    {
        $lead = $this->makeStoredHitLead('4045552003', 'National (USA) 2026-08-22;;;');
        $batch = ImportBatch::withoutGlobalScopes()->findOrFail($lead->import_batch_id);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => ['disposition' => Disposition::Dnc->value],
        ]);

        $result = app(ImportBatchCheckRetryService::class)->reapplyDncConsentPolicy($batch);

        $lead->refresh();
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(DncStatus::Hit, $lead->dnc_status);
        $this->assertSame(LeadStatus::Dnc, $lead->status);
    }

    public function test_phone_2_hit_scrubs_the_lead(): void
    {
        $this->configureDnc();

        $lead = $this->makeLead('4045556666', [
            'phone_2' => '4045557777',
        ]);

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('4045556666', $lead->id.':phone', 'C', ''),
                $this->scrubRow('4045557777', $lead->id.':phone_2', 'D', 'Litigator'),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$lead]));

        $lead->refresh();
        $this->assertSame(DncStatus::Hit, $lead->dnc_status);
        $this->assertSame(LeadStatus::Dnc, $lead->status);
        $this->assertSame('litigator', $lead->dnc_result['hit_reason']);
        $this->assertSame('C', $lead->dnc_result['phones']['phone']['result_code']);
        $this->assertSame('litigator', $lead->dnc_result['phones']['phone_2']['suppress']);
    }

    public function test_scrubs_multiple_leads_in_one_request(): void
    {
        $this->configureDnc();

        $first = $this->makeLead('4045558001');
        $second = $this->makeLead('4045558002');

        Http::fake([
            'www.dncscrub.com/app/main/rpc/scrub' => Http::response([
                $this->scrubRow('4045558001', $first->id.':phone', 'C', ''),
                $this->scrubRow('4045558002', $second->id.':phone', 'D', 'National (USA) 2018-08-16;;;'),
            ]),
        ]);

        app(DncService::class)->checkLeads(collect([$first, $second]));

        Http::assertSentCount(1);

        $first->refresh();
        $second->refresh();
        $this->assertSame(DncStatus::Clear, $first->dnc_status);
        $this->assertSame(DncStatus::Hit, $second->dnc_status);
    }

    public function test_pending_and_hit_dnc_leads_are_not_assignable(): void
    {
        $company = Company::factory()->create();

        $list = $this->createCallingList($company->id, overrides: [
            'name' => 'Standard List',
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551010',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'dnc_status' => DncStatus::Pending,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045552020',
            'status' => LeadStatus::Dnc,
            'lead_type' => 'standard',
            'dnc_status' => DncStatus::Hit,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045553030',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'dnc_status' => DncStatus::Clear,
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);
        $filter = new HoldingFilter(leadType: 'standard');

        $this->assertSame(1, $service->countHolding($company->id, $filter));
        $this->assertSame(1, $service->releaseAll($company->id, $filter, $list->id));
    }

    public function test_agent_dnc_disposition_pushes_phones_to_idnc(): void
    {
        $this->configureDnc();
        Queue::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559001',
            'phone_2' => '4045559002',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        app(DispositionService::class)->apply($lead, $user, Disposition::Dnc);

        Queue::assertPushed(DncPushJob::class, fn (DncPushJob $job): bool => $job->leadId === $lead->id);
        Queue::assertPushed(
            SalesforceDncPushJob::class,
            fn (SalesforceDncPushJob $job): bool => $job->leadId === $lead->id && $job->actorId === $user->id,
        );

        Http::fake([
            'www.dncscrub.com/app/main/rpc/pdnc' => Http::response('', 200),
        ]);

        (new DncPushJob($lead->id))->handle(app(DncService::class));

        Http::assertSent(function ($request) use ($lead): bool {
            if (! str_contains($request->url(), '/pdnc')) {
                return false;
            }

            $body = $request->body();

            return str_contains($body, 'actionType=add')
                && str_contains($body, 'projId=ONPNT')
                && str_contains($body, $lead->phone)
                && str_contains($body, (string) $lead->phone_2);
        });

        $this->assertDatabaseHas('lead_history', [
            'lead_id' => $lead->id,
            'event_type' => LeadHistoryType::DncPush->value,
        ]);
    }

    private function configureDnc(): void
    {
        config([
            'services.dnc.login_id' => 'test-login-id',
            'services.dnc.project_id' => 'ONPNT',
            'services.dnc.base_url' => 'https://www.dncscrub.com',
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
            'dnc_status' => DncStatus::Pending,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLeadWithBatch(string $phone, bool $ignoreNationalDnc, array $overrides = []): Lead
    {
        $company = Company::factory()->create();

        $batch = ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'dnc.csv',
            'imported_at' => now(),
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
            'run_dnc_check' => true,
            'ignore_national_dnc' => $ignoreNationalDnc,
        ]);

        return $this->makeLead($phone, array_merge([
            'company_id' => $company->id,
            'import_batch_id' => $batch->id,
        ], $overrides));
    }

    private function makeStoredHitLead(string $phone, string $reason): Lead
    {
        $lead = $this->makeLeadWithBatch($phone, ignoreNationalDnc: false);

        $lead->update([
            'status' => LeadStatus::Dnc,
            'dnc_status' => DncStatus::Hit,
            'dnc_result' => [
                'status' => DncStatus::Hit->value,
                'hit_reason' => 'national',
                'phones' => [
                    'phone' => [
                        'field' => 'phone',
                        'phone' => $phone,
                        'result_code' => 'D',
                        'reason' => $reason,
                        'suppress' => 'national',
                    ],
                ],
            ],
        ]);

        return $lead->refresh();
    }

    /**
     * @return array<string, string>
     */
    private function scrubRow(string $phone, string $reference, string $resultCode, string $reason): array
    {
        return [
            'Phone' => $phone,
            'ResultCode' => $resultCode,
            'Reserved' => $reference,
            'Reason' => $reason,
            'RegionAbbrev' => 'GA',
            'Country' => 'US',
            'Locale' => 'Atlanta',
            'CarrierInfo' => '',
            'LineType' => 'AllOther',
        ];
    }
}
