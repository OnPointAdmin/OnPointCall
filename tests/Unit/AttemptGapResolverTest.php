<?php

namespace Tests\Unit;

use App\Enums\CadenceWaitUnit;
use App\Enums\LeadStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Compliance\AttemptGapResolver;
use App\Support\CadenceProvisioner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class AttemptGapResolverTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_applies_minute_gap_after_first_attempt(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

        $company = Company::factory()->create();
        $cadence = $this->createCadence(
            $company->id,
            attemptGaps: [['after_attempt' => 1, 'wait_value' => 60, 'wait_unit' => 'minutes']],
        );
        $list = $this->createCallingList($company->id, $cadence);
        $lead = $this->makeLead($list->id, $company->id, attemptCount: 1, lastAttemptAt: now());

        $resolver = app(AttemptGapResolver::class);

        $this->assertFalse($resolver->isGapSatisfied($lead));
        Carbon::setTestNow(now()->addMinutes(60));
        $this->assertTrue($resolver->isGapSatisfied($lead));
    }

    public function test_applies_calendar_day_gap_after_fourth_attempt(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/New_York'));

        $company = Company::factory()->create();
        $cadence = CadenceProvisioner::create(
            companyId: $company->id,
            name: 'Standard',
            attemptGaps: [
                ['after_attempt' => 1, 'wait_value' => 60, 'wait_unit' => 'minutes'],
                ['after_attempt' => 4, 'wait_value' => 7, 'wait_unit' => 'days'],
            ],
        );
        $list = $this->createCallingList($company->id, $cadence);
        $lead = $this->makeLead(
            $list->id,
            $company->id,
            attemptCount: 4,
            lastAttemptAt: Carbon::parse('2026-08-10 14:00:00', 'America/New_York'),
        );

        $resolver = app(AttemptGapResolver::class);

        Carbon::setTestNow(Carbon::parse('2026-08-16 23:59:00', 'America/New_York'));
        $this->assertFalse($resolver->isGapSatisfied($lead));

        Carbon::setTestNow(Carbon::parse('2026-08-17 00:00:00', 'America/New_York'));
        $this->assertTrue($resolver->isGapSatisfied($lead));
    }

    public function test_no_matching_rule_means_no_extra_wait(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

        $company = Company::factory()->create();
        $cadence = CadenceProvisioner::create(
            companyId: $company->id,
            name: 'High threshold',
            attemptGaps: [
                ['after_attempt' => 5, 'wait_value' => 7, 'wait_unit' => CadenceWaitUnit::Days->value],
            ],
        );
        $list = $this->createCallingList($company->id, $cadence);
        $lead = $this->makeLead($list->id, $company->id, attemptCount: 2, lastAttemptAt: now()->subMinute());

        $this->assertTrue(app(AttemptGapResolver::class)->isGapSatisfied($lead));
    }

    private function makeLead(int $listId, int $companyId, int $attemptCount, Carbon $lastAttemptAt): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => '404555'.random_int(1000, 9999),
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $listId,
            'attempt_count' => $attemptCount,
            'last_attempt_at' => $lastAttemptAt,
            'imported_at' => now(),
        ])->load('callingList.cadence.attemptGaps');
    }
}
