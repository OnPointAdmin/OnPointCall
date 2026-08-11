<?php

namespace Tests\Feature;

use App\Enums\EmptyQueueReason;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Models\ListAssignment;
use App\Models\StateRule;
use App\Models\User;
use App\Services\Leads\NextLeadService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NextLeadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/New_York'));
    }

    public function test_returns_owned_callback_before_shared_pool(): void
    {
        [$user, $list] = $this->makeAgentWithList();

        $poolLead = $this->makeCallableLead($user->company_id, $list->id, '4045551001', 1);
        $callbackLead = Lead::withoutGlobalScopes()->create([
            'company_id' => $user->company_id,
            'phone' => '4045551002',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callback,
            'lead_type' => LeadType::Standard,
            'calling_list_id' => $list->id,
            'callback_owner_id' => $user->id,
            'callback_at' => now()->subHour(),
            'imported_at' => now(),
            'queue_rank' => 99,
        ]);

        $result = app(NextLeadService::class)->getNext($user);

        $this->assertTrue($result->hasLead());
        $this->assertSame($callbackLead->id, $result->lead?->id);
        $this->assertNotSame($poolLead->id, $result->lead?->id);
        $this->assertDatabaseHas('lead_claims', [
            'lead_id' => $callbackLead->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_returns_empty_reason_when_no_leads_exist(): void
    {
        [$user] = $this->makeAgentWithList();

        $result = app(NextLeadService::class)->getNext($user);

        $this->assertFalse($result->hasLead());
        $this->assertSame(EmptyQueueReason::NoneAvailable, $result->emptyReason);
    }

    /**
     * @return array{0: User, 1: CallingList}
     */
    private function makeAgentWithList(): array
    {
        $company = Company::factory()->create();

        StateRule::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'state_code' => 'NY',
            'window_start' => '08:00:00',
            'window_end' => '21:00:00',
            'permitted_weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'manual_dial_only' => false,
        ]);

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
        ]);

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'lead_type' => LeadType::Standard,
            'cadence' => [
                'day_parts' => ['morning', 'afternoon', 'evening'],
                'min_gap_minutes' => 60,
            ],
            'active' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
        ]);

        ListAssignment::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'calling_list_id' => $list->id,
        ]);

        return [$user, $list];
    }

    private function makeCallableLead(int $companyId, int $listId, string $phone, int $rank): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => $phone,
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => LeadType::Standard,
            'calling_list_id' => $listId,
            'imported_at' => now(),
            'queue_rank' => $rank,
        ]);
    }
}
