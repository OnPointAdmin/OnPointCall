<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\StateRule;
use App\Services\Compliance\ComplianceService;
use App\Support\CadenceDefaults;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class ComplianceServiceTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

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

    public function test_due_callback_skips_cadence_timing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 22:30:00', 'America/New_York'));

        $company = Company::factory()->create();
        $this->seedStateRule($company->id, 'NY');

        $list = $this->createCallingList($company->id);
        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559999',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callback,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'attempt_count' => 2,
            'last_attempt_at' => now()->subMinutes(5),
            'next_day_part' => 'morning',
            'imported_at' => now(),
        ])->load('callingList.cadence.dayParts', 'callingList.cadence.attemptGaps');

        $service = app(ComplianceService::class);

        $this->assertFalse($service->isWithinLegalWindow($lead));
        $this->assertFalse($service->canDialNow($lead));

        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

        $this->assertTrue($service->isWithinLegalWindow($lead));
        $this->assertTrue($service->canDialNow($lead));
        $this->assertFalse($service->isCadenceReady($lead));
    }

    public function test_never_dialed_here_lead_is_callable_between_day_part_windows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-25 11:30:00', 'America/New_York'));

        $company = Company::factory()->create();
        $this->seedStateRule($company->id, 'NY');

        $rows = CadenceDefaults::dayPartRows();
        $rows[0]['window_end'] = '11:00';
        $cadence = $this->createCadence($company->id, dayParts: $rows);
        $list = $this->createCallingList($company->id, $cadence);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551234',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'attempt_count' => 4,
            'imported_at' => now(),
            'queue_rank' => 1,
        ])->load('callingList.cadence.dayParts', 'callingList.cadence.attemptGaps');

        $service = app(ComplianceService::class);

        $this->assertTrue($service->isWithinLegalWindow($lead));
        $this->assertTrue($service->isCadenceReady($lead));
        $this->assertTrue($service->canDialNow($lead));
    }

    public function test_retried_lead_still_waits_for_next_day_part(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-25 11:30:00', 'America/New_York'));

        $company = Company::factory()->create();
        $this->seedStateRule($company->id, 'NY');

        $rows = CadenceDefaults::dayPartRows();
        $rows[0]['window_end'] = '11:00';
        $cadence = $this->createCadence($company->id, dayParts: $rows);
        $list = $this->createCallingList($company->id, $cadence);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551234',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'attempt_count' => 1,
            'last_attempt_at' => now()->subHours(2),
            'next_day_part' => 'afternoon',
            'imported_at' => now(),
            'queue_rank' => 1,
        ])->load('callingList.cadence.dayParts', 'callingList.cadence.attemptGaps');

        $service = app(ComplianceService::class);

        $this->assertTrue($service->isWithinLegalWindow($lead));
        $this->assertFalse($service->isCadenceReady($lead));
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
        $list = $this->createCallingList($companyId);

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
        ])->load('callingList.cadence.dayParts', 'callingList.cadence.attemptGaps');
    }
}
