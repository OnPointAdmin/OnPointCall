<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Models\StateRule;
use App\Services\Compliance\ComplianceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_is_callable_during_legal_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

        $company = Company::factory()->create();
        $this->seedStateRule($company->id, 'NY');

        $lead = $this->makeLead($company->id);

        $service = app(ComplianceService::class);

        $this->assertTrue($service->isWithinLegalWindow($lead));
        $this->assertTrue($service->canDialNow($lead));
    }

    public function test_lead_is_blocked_outside_legal_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 22:30:00', 'America/New_York'));

        $company = Company::factory()->create();
        $this->seedStateRule($company->id, 'NY');

        $lead = $this->makeLead($company->id);

        $service = app(ComplianceService::class);

        $this->assertFalse($service->isWithinLegalWindow($lead));
        $this->assertFalse($service->canDialNow($lead));
    }

    private function seedStateRule(int $companyId, string $state): void
    {
        StateRule::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'state_code' => $state,
            'window_start' => '08:00:00',
            'window_end' => '21:00:00',
            'permitted_weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'manual_dial_only' => false,
        ]);

        StateRule::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'state_code' => 'DEFAULT',
            'window_start' => '08:00:00',
            'window_end' => '21:00:00',
            'permitted_weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'manual_dial_only' => false,
        ]);
    }

    private function makeLead(int $companyId): Lead
    {
        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'name' => 'Standard',
            'lead_type' => 'standard',
            'cadence' => [
                'day_parts' => ['morning', 'afternoon', 'evening'],
                'min_gap_minutes' => 60,
            ],
            'active' => true,
        ]);

        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => '4045551234',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'imported_at' => now(),
            'queue_rank' => 1,
        ])->load('callingList');
    }
}
