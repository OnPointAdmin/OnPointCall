<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\EmptyQueueReason;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\DispositionReason;
use App\Models\Lead;
use App\Models\ListAssignment;
use App\Models\StateRule;
use App\Models\User;
use App\Services\Leads\DispositionService;
use App\Services\Leads\NextLeadService;
use App\Support\CadenceDefaults;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class NextLeadServiceTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

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
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'callback_owner_id' => $user->id,
            'callback_at' => now()->subHour(),
            'attempt_count' => 2,
            'last_attempt_at' => now()->subMinutes(5),
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

    public function test_migrated_lead_never_dialed_here_is_served_between_day_parts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-25 11:30:00', 'America/New_York'));

        $rows = CadenceDefaults::dayPartRows();
        $rows[0]['window_end'] = '11:00';

        [$user, $list] = $this->makeAgentWithList($rows);

        $lead = $this->makeCallableLead($user->company_id, $list->id, '4045551401', 1, attemptCount: 4);

        $result = app(NextLeadService::class)->getNext($user);

        $this->assertTrue($result->hasLead());
        $this->assertSame($lead->id, $result->lead?->id);
        $this->assertNull($lead->fresh()->last_attempt_at);
    }

    public function test_prioritizes_never_dialed_leads_when_cadence_flag_enabled(): void
    {
        [$user, $list] = $this->makeAgentWithList();

        $retried = $this->makeCallableLead($user->company_id, $list->id, '4045551001', 1, attemptCount: 2);
        $fresh = $this->makeCallableLead($user->company_id, $list->id, '4045551002', 2, attemptCount: 0);

        $result = app(NextLeadService::class)->getNext($user);

        $this->assertTrue($result->hasLead());
        $this->assertSame($fresh->id, $result->lead?->id);
        $this->assertNotSame($retried->id, $result->lead?->id);
    }

    public function test_skipped_lead_follows_cadence_and_is_not_re_served_immediately(): void
    {
        [$user, $list] = $this->makeAgentWithList();
        $reason = DispositionReason::withoutGlobalScopes()->create([
            'company_id' => $user->company_id,
            'disposition' => Disposition::Skip,
            'label' => 'Busy signal',
            'sort_order' => 1,
            'active' => true,
        ]);

        $skipped = $this->makeCallableLead($user->company_id, $list->id, '4045551101', 1);
        $second = $this->makeCallableLead($user->company_id, $list->id, '4045551102', 2);
        $third = $this->makeCallableLead($user->company_id, $list->id, '4045551103', 3);

        $service = app(NextLeadService::class);
        $dispositions = app(DispositionService::class);

        $first = $service->getNext($user);
        $this->assertSame($skipped->id, $first->lead?->id);

        $dispositions->apply($first->lead, $user, Disposition::Skip, reason: (string) $reason->id);

        $next = $service->getNext($user);
        $this->assertSame($second->id, $next->lead?->id);

        $dispositions->apply($next->lead, $user, Disposition::Skip, reason: (string) $reason->id);

        $afterTwoSkips = $service->getNext($user);
        $this->assertSame($third->id, $afterTwoSkips->lead?->id);

        $dispositions->apply($afterTwoSkips->lead, $user, Disposition::Skip, reason: (string) $reason->id);

        $result = $service->getNext($user);
        $this->assertFalse($result->hasLead());
        $this->assertSame(EmptyQueueReason::BlockedByCadence, $result->emptyReason);

        $skipped = $skipped->fresh();
        $this->assertSame(0, $skipped->attempt_count);
        $this->assertSame('evening', $skipped->next_day_part);
        $this->assertNotNull($skipped->last_attempt_at);
    }

    public function test_skipped_lead_is_preferred_for_a_different_agent(): void
    {
        [$agentA, $list] = $this->makeAgentWithList();
        $agentB = $this->makeAgentOnList($list);

        $skippedByA = $this->makeCallableLead(
            $agentA->company_id,
            $list->id,
            '4045551201',
            1,
            lastSkippedByUserId: $agentA->id,
        );
        $other = $this->makeCallableLead($agentA->company_id, $list->id, '4045551202', 2);

        $forA = app(NextLeadService::class)->getNext($agentA);
        $this->assertSame($other->id, $forA->lead?->id);

        $forB = app(NextLeadService::class)->getNext($agentB);
        $this->assertSame($skippedByA->id, $forB->lead?->id);
    }

    public function test_skipper_still_receives_lead_when_pool_has_nothing_else(): void
    {
        [$agent, $list] = $this->makeAgentWithList();

        $skipped = $this->makeCallableLead(
            $agent->company_id,
            $list->id,
            '4045551301',
            1,
            lastSkippedByUserId: $agent->id,
        );

        $result = app(NextLeadService::class)->getNext($agent);

        $this->assertTrue($result->hasLead());
        $this->assertSame($skipped->id, $result->lead?->id);
    }

    /**
     * @param  list<array<string, mixed>>|null  $dayParts
     * @return array{0: User, 1: CallingList}
     */
    private function makeAgentWithList(?array $dayParts = null): array
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

        $cadence = $dayParts === null ? null : $this->createCadence($company->id, dayParts: $dayParts);
        $list = $this->createCallingList($company->id, $cadence);

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

    private function makeAgentOnList(CallingList $list): User
    {
        $user = User::factory()->create([
            'company_id' => $list->company_id,
            'role' => UserRole::Agent,
            'active' => true,
        ]);

        ListAssignment::withoutGlobalScopes()->create([
            'company_id' => $list->company_id,
            'user_id' => $user->id,
            'calling_list_id' => $list->id,
        ]);

        return $user;
    }

    private function makeCallableLead(
        int $companyId,
        int $listId,
        string $phone,
        int $rank,
        int $attemptCount = 0,
        ?int $lastSkippedByUserId = null,
    ): Lead {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => $phone,
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $listId,
            'attempt_count' => $attemptCount,
            'last_skipped_by_user_id' => $lastSkippedByUserId,
            'imported_at' => now(),
            'queue_rank' => $rank,
        ]);
    }
}
