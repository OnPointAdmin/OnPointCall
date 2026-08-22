<?php

namespace Tests\Feature;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Exceptions\HoldingReleaseException;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Import\HoldingReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class HoldingReleaseServiceTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_release_assigns_leads_to_calling_list_with_matching_type(): void
    {
        $company = Company::factory()->create();

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard List',
            'lead_type' => 'standard',
            'cadence_id' => $this->createCadence($company->id)->id,
            'active' => true,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045552222',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);

        $released = $service->releaseAll(
            $company->id,
            new HoldingFilter(leadType: 'standard'),
            $list->id,
        );

        $this->assertSame(1, $released);

        $lead->refresh();

        $this->assertSame(LeadStatus::Callable, $lead->status);
        $this->assertSame($list->id, $lead->calling_list_id);
        $this->assertSame(1, $lead->queue_rank);
    }

    public function test_release_rejects_mismatched_lead_type(): void
    {
        $company = Company::factory()->create();

        $tnbList = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'TNB List',
            'lead_type' => 'tnb',
            'cadence_id' => $this->createCadence($company->id)->id,
            'active' => true,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045553333',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);

        $this->expectException(HoldingReleaseException::class);

        $service->releaseAll(
            $company->id,
            new HoldingFilter(leadType: 'standard'),
            $tnbList->id,
        );
    }

    public function test_distinct_holding_column_returns_only_holding_values_for_lead_type(): void
    {
        $company = Company::factory()->create();

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045554444',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'age_range' => '35-44',
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045555555',
            'status' => LeadStatus::Holding,
            'lead_type' => 'tnb',
            'age_range' => '45-54',
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045556666',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'age_range' => '55-64',
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);

        $this->assertSame(
            ['35-44' => '35-44'],
            $service->distinctHoldingColumn($company->id, 'standard', 'age_range'),
        );
    }

    public function test_distinct_holding_partners_splits_comma_separated_values(): void
    {
        $company = Company::factory()->create();

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045557777',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'partner_list' => '  Alpha , Beta  ',
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045558888',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'partner_list' => 'Beta, Gamma',
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045558889',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'partner_list' => 'Acme Partners, LLC, and Delta',
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);

        $this->assertSame(
            [
                'Acme Partners, LLC' => 'Acme Partners, LLC',
                'Alpha' => 'Alpha',
                'Beta' => 'Beta',
                'Delta' => 'Delta',
                'Gamma' => 'Gamma',
            ],
            $service->distinctHoldingPartners($company->id, 'standard'),
        );
    }

    public function test_query_holding_filters_by_demographic_and_tour_fields(): void
    {
        $company = Company::factory()->create();

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559999',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'age_range' => '35-44',
            'gender' => 'F',
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045550000',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'age_range' => '45-54',
            'gender' => 'M',
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551111',
            'status' => LeadStatus::Holding,
            'lead_type' => 'tnb',
            'tour_location' => 'Orlando Resort',
            'tour_date_start' => '2026-09-01',
            'tour_date' => '2026-09-15',
            'tour_result' => 'Completed',
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551212',
            'status' => LeadStatus::Holding,
            'lead_type' => 'tnb',
            'tour_location' => 'Miami Beach',
            'tour_date_start' => '2026-10-01',
            'tour_date' => '2026-10-15',
            'tour_result' => 'No Buy',
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);

        $this->assertSame(
            1,
            $service->countHolding($company->id, new HoldingFilter(
                leadType: 'standard',
                ageRange: ['35-44'],
                gender: ['F'],
            )),
        );

        $this->assertSame(
            2,
            $service->countHolding($company->id, new HoldingFilter(
                leadType: 'standard',
                ageRange: ['35-44', '45-54'],
            )),
        );

        $this->assertSame(
            1,
            $service->countHolding($company->id, new HoldingFilter(
                leadType: 'tnb',
                tourLocation: ['Orlando Resort'],
                tourDateStart: ['2026-09-01'],
                tourDate: ['2026-09-15'],
                tourResult: ['Completed'],
            )),
        );

        $this->assertSame(
            2,
            $service->countHolding($company->id, new HoldingFilter(
                leadType: 'tnb',
                tourLocation: ['Orlando Resort', 'Miami Beach'],
            )),
        );
    }

    public function test_distinct_holding_column_rejects_unknown_columns(): void
    {
        $company = Company::factory()->create();
        $service = app(HoldingReleaseService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->distinctHoldingColumn($company->id, 'standard', 'not_a_column');
    }

    public function test_pending_rnd_leads_are_not_assignable(): void
    {
        $company = Company::factory()->create();

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard List',
            'lead_type' => 'standard',
            'cadence_id' => $this->createCadence($company->id)->id,
            'active' => true,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551010',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'rnd_status' => RndStatus::Pending,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045552020',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'rnd_status' => RndStatus::Clear,
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);
        $filter = new HoldingFilter(leadType: 'standard');

        $this->assertSame(1, $service->countHolding($company->id, $filter));
        $this->assertSame(1, $service->releaseAll($company->id, $filter, $list->id));
    }

    public function test_reassigned_rnd_leads_are_not_assignable(): void
    {
        $company = Company::factory()->create();

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard List',
            'lead_type' => 'standard',
            'cadence_id' => $this->createCadence($company->id)->id,
            'active' => true,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045553030',
            'status' => LeadStatus::Terminal,
            'lead_type' => 'standard',
            'rnd_status' => RndStatus::Reassigned,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045554040',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'rnd_status' => RndStatus::Clear,
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);
        $filter = new HoldingFilter(leadType: 'standard');

        $this->assertSame(1, $service->countHolding($company->id, $filter));
        $this->assertSame(1, $service->releaseAll($company->id, $filter, $list->id));
    }

    public function test_pending_soft_score_leads_are_not_assignable(): void
    {
        $company = Company::factory()->create();

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard List',
            'lead_type' => 'standard',
            'cadence_id' => $this->createCadence($company->id)->id,
            'active' => true,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045555050',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'soft_score_status' => SoftScoreStatus::Pending,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045556060',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'soft_score_status' => SoftScoreStatus::Complete,
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);
        $filter = new HoldingFilter(leadType: 'standard');

        $this->assertSame(1, $service->countHolding($company->id, $filter));
        $this->assertSame(1, $service->releaseAll($company->id, $filter, $list->id));
    }

    public function test_soft_score_error_leads_are_assignable(): void
    {
        $company = Company::factory()->create();

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard List',
            'lead_type' => 'standard',
            'cadence_id' => $this->createCadence($company->id)->id,
            'active' => true,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045557070',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'rnd_status' => RndStatus::Clear,
            'soft_score_status' => SoftScoreStatus::Error,
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);
        $filter = new HoldingFilter(leadType: 'standard');

        $this->assertSame(1, $service->countHolding($company->id, $filter));
        $this->assertSame(1, $service->releaseAll($company->id, $filter, $list->id));
    }

    public function test_pending_qualification_leads_are_not_assignable(): void
    {
        $company = Company::factory()->create();

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard List',
            'lead_type' => 'standard',
            'cadence_id' => $this->createCadence($company->id)->id,
            'active' => true,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045558080',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::Pending,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559090',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::Qualified,
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);
        $filter = new HoldingFilter(leadType: 'standard');

        $this->assertSame(1, $service->countHolding($company->id, $filter));
        $this->assertSame(1, $service->releaseAll($company->id, $filter, $list->id));
    }

    public function test_qualification_status_filter_counts_only_qualified_leads(): void
    {
        $company = Company::factory()->create();

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551111',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::Qualified,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045552222',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::NotQualified,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045553333',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => null,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045554444',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::Error,
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);

        $this->assertSame(
            1,
            $service->countHolding($company->id, new HoldingFilter(
                leadType: 'standard',
                qualificationStatus: QualificationStatus::Qualified->value,
            )),
        );
    }

    public function test_qualification_status_filter_counts_only_not_qualified_leads(): void
    {
        $company = Company::factory()->create();

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045555555',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::Qualified,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045556666',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::NotQualified,
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);

        $this->assertSame(
            1,
            $service->countHolding($company->id, new HoldingFilter(
                leadType: 'standard',
                qualificationStatus: QualificationStatus::NotQualified->value,
            )),
        );
    }

    public function test_blank_qualification_status_filter_includes_all_assignable_statuses(): void
    {
        $company = Company::factory()->create();

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045557777',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::Qualified,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045558888',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::NotQualified,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559999',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => null,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045550000',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::Error,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045550101',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::Pending,
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);

        $this->assertSame(
            4,
            $service->countHolding($company->id, new HoldingFilter(leadType: 'standard')),
        );
    }

    public function test_release_fresh_assigns_n_newest_leads_by_imported_at(): void
    {
        $company = Company::factory()->create();

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard List',
            'lead_type' => 'standard',
            'cadence_id' => $this->createCadence($company->id)->id,
            'active' => true,
        ]);

        $oldest = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551001',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now()->subDays(3),
        ]);

        $middle = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551002',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now()->subDays(2),
        ]);

        $newest = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551003',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now()->subDay(),
        ]);

        $service = app(HoldingReleaseService::class);
        $filter = new HoldingFilter(leadType: 'standard');

        $this->assertSame(3, $service->countHolding($company->id, $filter));
        $this->assertSame(2, $service->releaseFresh($company->id, $filter, $list->id, 2));

        $oldest->refresh();
        $middle->refresh();
        $newest->refresh();

        $this->assertSame(LeadStatus::Holding, $oldest->status);
        $this->assertNull($oldest->calling_list_id);

        $this->assertSame(LeadStatus::Callable, $middle->status);
        $this->assertSame($list->id, $middle->calling_list_id);

        $this->assertSame(LeadStatus::Callable, $newest->status);
        $this->assertSame($list->id, $newest->calling_list_id);
    }

    public function test_release_fresh_rejects_count_below_one(): void
    {
        $company = Company::factory()->create();

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard List',
            'lead_type' => 'standard',
            'cadence_id' => $this->createCadence($company->id)->id,
            'active' => true,
        ]);

        $service = app(HoldingReleaseService::class);

        $this->expectException(HoldingReleaseException::class);

        $service->releaseFresh(
            $company->id,
            new HoldingFilter(leadType: 'standard'),
            $list->id,
            0,
        );
    }
}
