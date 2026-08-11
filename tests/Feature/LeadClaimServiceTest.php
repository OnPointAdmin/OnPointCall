<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadClaim;
use App\Models\User;
use App\Services\Leads\LeadClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadClaimServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_stale_claims(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045554001',
            'status' => LeadStatus::Callable,
            'lead_type' => LeadType::Standard,
            'imported_at' => now(),
        ]);

        LeadClaim::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'claimed_at' => now()->subHour(),
            'expires_at' => now()->subMinute(),
        ]);

        $expired = app(LeadClaimService::class)->expireStaleClaims($company->id);

        $this->assertSame(1, $expired);
        $this->assertDatabaseMissing('lead_claims', ['lead_id' => $lead->id]);
    }
}
