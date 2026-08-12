<?php

namespace Tests\Feature;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\LeadStatus;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadTypeDefinition;
use App\Services\Import\HoldingReleaseService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTypeDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_from_name_generates_slug_and_is_company_scoped(): void
    {
        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $type = LeadTypeDefinition::createFromName('Summer Venue 2026');

        $this->assertSame('summer-venue-2026', $type->slug);
        $this->assertSame('Summer Venue 2026', $type->name);
        $this->assertTrue($type->active);
        $this->assertSame($company->id, $type->company_id);

        $again = LeadTypeDefinition::createFromName('Summer Venue 2026');
        $this->assertSame($type->id, $again->id);

        CompanyContext::clear();
    }

    public function test_holding_filter_and_release_work_with_custom_lead_type(): void
    {
        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        LeadTypeDefinition::createFromName('Custom Batch');

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Custom List',
            'lead_type' => 'custom-batch',
            'cadence' => [],
            'active' => true,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551212',
            'status' => LeadStatus::Holding,
            'lead_type' => 'custom-batch',
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551313',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $service = app(HoldingReleaseService::class);

        $this->assertSame(
            1,
            $service->countHolding($company->id, new HoldingFilter(leadType: 'custom-batch')),
        );

        $released = $service->releaseAll(
            $company->id,
            new HoldingFilter(leadType: 'custom-batch'),
            $list->id,
        );

        $this->assertSame(1, $released);

        CompanyContext::clear();
    }

    public function test_slug_must_be_unique_per_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        CompanyContext::set($companyA->id);
        LeadTypeDefinition::createFromName('Standard', 'standard');
        CompanyContext::clear();

        CompanyContext::set($companyB->id);
        $typeB = LeadTypeDefinition::createFromName('Standard', 'standard');
        CompanyContext::clear();

        $this->assertSame('standard', $typeB->slug);
        $this->assertSame($companyB->id, $typeB->company_id);
        $this->assertSame(2, LeadTypeDefinition::withoutGlobalScopes()->where('slug', 'standard')->count());
    }
}
