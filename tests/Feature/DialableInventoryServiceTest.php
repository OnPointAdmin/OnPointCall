<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadClaim;
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
    ): Lead {
        return $this->makeLead(
            $companyId,
            $listId,
            LeadStatus::Callable,
            $phone,
            $attemptCount,
            $lastAttemptAt,
        );
    }

    private function makeLead(
        int $companyId,
        int $listId,
        LeadStatus $status,
        string $phone,
        int $attemptCount = 0,
        ?Carbon $lastAttemptAt = null,
    ): Lead {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => $phone,
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => $status,
            'lead_type' => 'standard',
            'calling_list_id' => $listId,
            'attempt_count' => $attemptCount,
            'last_attempt_at' => $lastAttemptAt,
            'imported_at' => now(),
        ]);
    }
}
