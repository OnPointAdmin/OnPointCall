<?php

namespace Tests\Feature;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Exceptions\HoldingReleaseException;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Import\HoldingReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'lead_type' => LeadType::Standard,
            'cadence' => [],
            'active' => true,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045552222',
            'status' => LeadStatus::Holding,
            'lead_type' => LeadType::Standard,
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);

        $released = $service->releaseAll(
            $company->id,
            new HoldingFilter(leadType: LeadType::Standard),
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
            'lead_type' => LeadType::Tnb,
            'cadence' => [],
            'active' => true,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045553333',
            'status' => LeadStatus::Holding,
            'lead_type' => LeadType::Standard,
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);

        $this->expectException(HoldingReleaseException::class);

        $service->releaseAll(
            $company->id,
            new HoldingFilter(leadType: LeadType::Standard),
            $tnbList->id,
        );
    }
}
