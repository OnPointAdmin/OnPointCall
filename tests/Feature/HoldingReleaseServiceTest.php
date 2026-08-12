<?php

namespace Tests\Feature;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\LeadStatus;
use App\Exceptions\HoldingReleaseException;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Import\HoldingReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class HoldingReleaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_assigns_leads_to_calling_list_with_matching_type(): void
    {
        $company = Company::factory()->create();

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard List',
            'lead_type' => 'standard',
            'cadence' => [],
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
            'cadence' => [],
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
            'partner_list' => 'Alpha, Beta',
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

        $service = app(HoldingReleaseService::class);

        $this->assertSame(
            [
                'Alpha' => 'Alpha',
                'Beta' => 'Beta',
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
            'tour_date' => '2026-09-15',
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);

        $this->assertSame(
            1,
            $service->countHolding($company->id, new HoldingFilter(
                leadType: 'standard',
                ageRange: '35-44',
                gender: 'F',
            )),
        );

        $this->assertSame(
            1,
            $service->countHolding($company->id, new HoldingFilter(
                leadType: 'tnb',
                tourLocation: 'Orlando Resort',
                tourDate: '2026-09-15',
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
}
