<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use App\Services\Leads\LeadLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_minimum_query_length(): void
    {
        $company = Company::factory()->create();

        $results = app(LeadLookupService::class)->search($company->id, 'ab');

        $this->assertCount(0, $results);
    }

    public function test_search_returns_matching_leads_up_to_cap(): void
    {
        $company = Company::factory()->create();

        for ($i = 0; $i < 12; $i++) {
            Lead::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'phone' => '404555'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'first_name' => 'John',
                'state' => 'NY',
                'timezone' => 'America/New_York',
                'status' => LeadStatus::Callable,
                'lead_type' => 'standard',
                'imported_at' => now(),
                'queue_rank' => $i,
            ]);
        }

        $results = app(LeadLookupService::class)->search($company->id, 'John');

        $this->assertCount(LeadLookupService::MAX_RESULTS, $results);
    }

    public function test_terminal_lead_is_read_only(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => UserRole::Agent]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559999',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Booked,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'queue_rank' => 1,
        ]);

        $service = app(LeadLookupService::class);

        $this->assertTrue($service->isReadOnly($lead, $user));
        $this->assertFalse($service->canWorkImmediately($lead, $user));
    }
}
