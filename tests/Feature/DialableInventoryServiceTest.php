<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadClaim;
use App\Models\LeadHistory;
use App\Models\StateRule;
use App\Models\User;
use App\Services\Leads\DialableInventoryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class DialableInventoryServiceTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));
    }

    public function test_never_dialed_callable_lead_counts_as_ready_now(): void
    {
        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id);
        $this->makeCallableLead($company->id, $list->id, '4045551001');

        $inventory = app(DialableInventoryService::class)->forList($list);

        $this->assertSame(1, $inventory->readyNow);
        $this->assertSame(0, $inventory->waiting);
    }

    public function test_cadence_blocked_lead_counts_as_waiting(): void
    {
        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id, $this->createCadenceWithDayParts($company->id));
        $this->makeCallableLead(
            $company->id,
            $list->id,
            '4045551001',
            attemptCount: 1,
            lastAttemptAt: now()->subMinutes(5),
        );

        $inventory = app(DialableInventoryService::class)->forList($list);

        $this->assertSame(0, $inventory->readyNow);
        $this->assertSame(1, $inventory->waiting);
        $this->assertSame(1, $inventory->waitingCadence);
        $this->assertSame(0, $inventory->waitingHours);
        $this->assertSame(0, $inventory->claimed);
    }

    public function test_cadence_wait_splits_by_next_day_part(): void
    {
        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id, $this->createCadenceWithDayParts($company->id));
        $this->makeCallableLead(
            $company->id,
            $list->id,
            '4045551001',
            attemptCount: 1,
            lastAttemptAt: now()->subMinutes(5),
            nextDayPart: 'morning',
        );

        $inventory = app(DialableInventoryService::class)->forList($list);

        $this->assertSame(0, $inventory->readyNow);
        $this->assertSame(1, $inventory->waitingCadence);
        $this->assertSame(1, $inventory->cadenceByDayPart['morning']);
        $this->assertSame('1 morning', $inventory->cadenceDayPartDescription());
        $this->assertNotNull($inventory->cadenceEarliestByPart['morning']);
        $slots = $inventory->cadenceWaitSlotRows();
        $this->assertNotEmpty($slots);

        $tomorrowMorning = collect($slots)->first(
            fn (array $slot): bool => str_contains($slot['label'], 'Tomorrow') && str_contains($slot['label'], 'Morning'),
        );
        $this->assertNotNull($tomorrowMorning);
        $this->assertSame(1, $tomorrowMorning['count']);
        $this->assertSame(1, collect($slots)->sum('count'));

        $this->assertNull(collect($slots)->first(
            fn (array $slot): bool => str_contains($slot['label'], 'Today') && str_contains($slot['label'], 'Morning'),
        ));
        $this->assertNull(collect($slots)->first(
            fn (array $slot): bool => str_contains($slot['label'], 'Today') && str_contains($slot['label'], 'Afternoon'),
        ));
    }

    public function test_afternoon_call_shows_evening_cadence_slot(): void
    {
        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id, $this->createCadenceWithDayParts($company->id));
        $this->makeCallableLead(
            $company->id,
            $list->id,
            '4045551001',
            attemptCount: 1,
            lastAttemptAt: now()->subMinutes(5),
            nextDayPart: 'evening',
        );

        $inventory = app(DialableInventoryService::class)->forList($list);
        $slots = $inventory->cadenceWaitSlotRows();

        $this->assertSame(0, $inventory->readyNow);
        $this->assertSame(1, $inventory->waitingCadence);
        $this->assertSame(1, $inventory->cadenceByDayPart['evening']);

        $evening = collect($slots)->first(
            fn (array $slot): bool => str_contains($slot['label'], 'Evening'),
        );
        $this->assertNotNull($evening);
        $this->assertSame(1, $evening['count']);
        $this->assertStringContainsString('Tomorrow', $evening['label']);
    }

    public function test_leftover_evening_wait_shows_today_evening_during_morning(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-11 10:00:00', 'America/New_York'));

        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id, $this->createCadenceWithDayParts($company->id));
        $this->makeCallableLead(
            $company->id,
            $list->id,
            '4045551001',
            attemptCount: 1,
            lastAttemptAt: Carbon::parse('2026-08-10 15:00:00', 'America/New_York'),
            nextDayPart: 'evening',
        );

        $inventory = app(DialableInventoryService::class)->forList($list);
        $slots = $inventory->cadenceWaitSlotRows();

        $this->assertSame(0, $inventory->readyNow);
        $this->assertSame(1, $inventory->waitingCadence);

        $evening = collect($slots)->first(
            fn (array $slot): bool => str_contains($slot['label'], 'Evening'),
        );
        $this->assertNotNull($evening);
        $this->assertSame(1, $evening['count']);
        $this->assertStringContainsString('Today', $evening['label']);
    }

    public function test_evening_wait_stays_labeled_after_company_evening_window_starts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 18:00:00', 'America/New_York'));

        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id, $this->createCadenceWithDayParts($company->id));
        $this->makeCallableLead(
            $company->id,
            $list->id,
            '2065551001',
            attemptCount: 1,
            lastAttemptAt: Carbon::parse('2026-08-09 15:00:00', 'America/Los_Angeles'),
            nextDayPart: 'evening',
            timezone: 'America/Los_Angeles',
        );

        $inventory = app(DialableInventoryService::class)->forList($list);
        $slots = $inventory->cadenceWaitSlotRows();
        $labels = collect($slots)->pluck('label')->implode(' | ');

        $this->assertSame(0, $inventory->readyNow);
        $this->assertSame(1, $inventory->waitingCadence);
        $this->assertStringContainsString('Evening', $labels);
        $this->assertStringNotContainsString('After tomorrow', $labels);
    }

    public function test_ready_now_during_evening_names_the_evening_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 18:00:00', 'America/New_York'));

        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id, $this->createCadenceWithDayParts($company->id));
        $this->makeCallableLead(
            $company->id,
            $list->id,
            '4045551001',
            attemptCount: 1,
            lastAttemptAt: Carbon::parse('2026-08-09 15:00:00', 'America/New_York'),
            nextDayPart: 'evening',
        );

        $inventory = app(DialableInventoryService::class)->forList($list);

        $this->assertSame(1, $inventory->readyNow);
        $this->assertSame(0, $inventory->waitingCadence);
        $this->assertSame(['Evening'], $inventory->readyNowDayPartSummary());
        $this->assertSame('Evening', $inventory->readyNowDayPartDescription());
    }

    public function test_later_evening_wait_keeps_evening_label(): void
    {
        $company = $this->makeCompany();
        $list = $this->createCallingList(
            $company->id,
            $this->createCadenceWithDayParts(
                $company->id,
                attemptGaps: [['after_attempt' => 1, 'wait_value' => 3, 'wait_unit' => 'days']],
            ),
        );
        $this->makeCallableLead(
            $company->id,
            $list->id,
            '4045551001',
            attemptCount: 1,
            lastAttemptAt: now()->subMinutes(5),
            nextDayPart: 'evening',
        );

        $inventory = app(DialableInventoryService::class)->forList($list);
        $slots = $inventory->cadenceWaitSlotRows();

        $evening = collect($slots)->first(
            fn (array $slot): bool => str_contains($slot['label'], 'Evening'),
        );
        $this->assertNotNull($evening);
        $this->assertSame(1, $evening['count']);
        $this->assertStringContainsString('After tomorrow', $evening['label']);
    }

    public function test_outside_legal_hours_counts_as_waiting(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 22:30:00', 'America/New_York'));

        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id);
        $this->makeCallableLead($company->id, $list->id, '4045551001');

        $inventory = app(DialableInventoryService::class)->forList($list);

        $this->assertSame(0, $inventory->readyNow);
        $this->assertSame(1, $inventory->waiting);
        $this->assertSame(1, $inventory->waitingHours);
    }

    public function test_claimed_lead_counts_as_waiting(): void
    {
        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id);
        $lead = $this->makeCallableLead($company->id, $list->id, '4045551001');
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        LeadClaim::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'claimed_at' => now(),
            'expires_at' => now()->addMinutes(20),
        ]);

        $inventory = app(DialableInventoryService::class)->forList($list);

        $this->assertSame(0, $inventory->readyNow);
        $this->assertSame(1, $inventory->waiting);
        $this->assertSame(1, $inventory->claimed);
    }

    public function test_ignores_non_callable_and_exhausted_leads(): void
    {
        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id, overrides: ['max_attempts_override' => 2]);

        $this->makeCallableLead($company->id, $list->id, '4045551001');
        $this->makeCallableLead($company->id, $list->id, '4045551002', attemptCount: 2);
        $this->makeLead($company->id, $list->id, LeadStatus::Booked, '4045551003');
        $this->makeLead($company->id, $list->id, LeadStatus::Callback, '4045551004');

        $inventory = app(DialableInventoryService::class)->forList($list);

        $this->assertSame(1, $inventory->readyNow);
        $this->assertSame(0, $inventory->waiting);
        $this->assertSame(1, $inventory->maxAttempts);
        $this->assertSame(1, $inventory->callbacksScheduled);
    }

    public function test_due_callback_counts_separately_from_callable_pool(): void
    {
        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id);

        $this->makeLead(
            $company->id,
            $list->id,
            LeadStatus::Callback,
            '4045551001',
            callbackAt: now()->subHour(),
        );
        $this->makeLead(
            $company->id,
            $list->id,
            LeadStatus::Callback,
            '4045551002',
            callbackAt: now()->addHour(),
        );

        $inventory = app(DialableInventoryService::class)->forList($list);

        $this->assertSame(1, $inventory->callbacksDue);
        $this->assertSame(1, $inventory->callbacksScheduled);
        $this->assertSame(0, $inventory->readyNow);
    }

    public function test_counts_are_isolated_per_list(): void
    {
        $company = $this->makeCompany();
        $cadence = $this->createCadenceWithDayParts($company->id);
        $readyList = $this->createCallingList($company->id, $cadence, ['name' => 'Ready']);
        $waitingList = $this->createCallingList($company->id, $cadence, ['name' => 'Waiting']);

        $this->makeCallableLead($company->id, $readyList->id, '4045551001');
        $this->makeCallableLead(
            $company->id,
            $waitingList->id,
            '4045551002',
            attemptCount: 1,
            lastAttemptAt: now()->subMinutes(5),
        );

        $service = app(DialableInventoryService::class);

        $this->assertSame(1, $service->forList($readyList)->readyNow);
        $this->assertSame(0, $service->forList($readyList)->waiting);
        $this->assertSame(0, $service->forList($waitingList)->readyNow);
        $this->assertSame(1, $service->forList($waitingList)->waiting);
    }

    public function test_active_today_includes_list_with_disposition_today(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
        ]);
        $dialedList = $this->createCallingList($company->id, overrides: ['name' => 'Dialed Today']);
        $idleList = $this->createCallingList($company->id, overrides: ['name' => 'Idle']);
        $lead = $this->makeCallableLead($company->id, $dialedList->id, '4045552001');
        $this->makeCallableLead($company->id, $idleList->id, '4045552002');

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => ['disposition' => Disposition::NoAnswer->value],
        ]);

        $active = app(DialableInventoryService::class)->activeTodayForCompany($company->id);

        $this->assertCount(1, $active);
        $this->assertSame('Dialed Today', $active[0]['list']->name);
        $this->assertSame(1, $active[0]['inventory']->readyNow);
    }

    public function test_active_today_includes_list_with_only_active_claim(): void
    {
        $company = $this->makeCompany();
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);
        $claimedList = $this->createCallingList($company->id, overrides: ['name' => 'Claimed']);
        $idleList = $this->createCallingList($company->id, overrides: ['name' => 'Idle']);
        $lead = $this->makeCallableLead($company->id, $claimedList->id, '4045553001');
        $this->makeCallableLead($company->id, $idleList->id, '4045553002');

        LeadClaim::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'claimed_at' => now(),
            'expires_at' => now()->addMinutes(20),
        ]);

        $active = app(DialableInventoryService::class)->activeTodayForCompany($company->id);

        $this->assertCount(1, $active);
        $this->assertSame('Claimed', $active[0]['list']->name);
        $this->assertSame(1, $active[0]['inventory']->claimed);
    }

    public function test_active_today_excludes_idle_lists(): void
    {
        $company = $this->makeCompany();
        $idleList = $this->createCallingList($company->id, overrides: ['name' => 'Idle']);
        $this->makeCallableLead($company->id, $idleList->id, '4045554001');

        $active = app(DialableInventoryService::class)->activeTodayForCompany($company->id);

        $this->assertSame([], $active);
    }

    public function test_active_today_includes_exhausted_list_with_zero_inventory(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
        ]);
        $list = $this->createCallingList($company->id, overrides: ['name' => 'Worked Out']);
        $lead = $this->makeLead($company->id, $list->id, LeadStatus::Booked, '4045555001');

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => ['disposition' => Disposition::Booked->value],
        ]);

        $active = app(DialableInventoryService::class)->activeTodayForCompany($company->id);

        $this->assertCount(1, $active);
        $this->assertSame('Worked Out', $active[0]['list']->name);
        $this->assertSame(0, $active[0]['inventory']->readyNow);
        $this->assertSame(0, $active[0]['inventory']->waiting);
        $this->assertNotNull($active[0]['inventory']->timezone);
    }

    private function makeCompany(): Company
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

        StateRule::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'state_code' => 'DEFAULT',
            'window_start' => '08:00:00',
            'window_end' => '21:00:00',
            'permitted_weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'manual_dial_only' => false,
        ]);

        return $company;
    }

    private function makeCallableLead(
        int $companyId,
        int $listId,
        string $phone,
        int $attemptCount = 0,
        ?Carbon $lastAttemptAt = null,
        ?string $nextDayPart = null,
        string $timezone = 'America/New_York',
    ): Lead {
        return $this->makeLead(
            $companyId,
            $listId,
            LeadStatus::Callable,
            $phone,
            $attemptCount,
            $lastAttemptAt,
            $nextDayPart,
            timezone: $timezone,
        );
    }

    private function makeLead(
        int $companyId,
        int $listId,
        LeadStatus $status,
        string $phone,
        int $attemptCount = 0,
        ?Carbon $lastAttemptAt = null,
        ?string $nextDayPart = null,
        ?Carbon $callbackAt = null,
        string $timezone = 'America/New_York',
    ): Lead {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => $phone,
            'state' => 'NY',
            'timezone' => $timezone,
            'status' => $status,
            'lead_type' => 'standard',
            'calling_list_id' => $listId,
            'attempt_count' => $attemptCount,
            'last_attempt_at' => $lastAttemptAt,
            'next_day_part' => $nextDayPart,
            'callback_at' => $callbackAt,
            'imported_at' => now(),
        ]);
    }
}
