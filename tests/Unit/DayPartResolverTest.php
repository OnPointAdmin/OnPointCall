<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Lead;
use App\Services\Compliance\DayPartResolver;
use App\Support\CadenceDefaults;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class DayPartResolverTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_first_morning_call_schedules_afternoon_next(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00', 'America/New_York'));

        $lead = $this->makeLead();

        $resolver = app(DayPartResolver::class);

        $this->assertTrue($resolver->matchesNextDayPart($lead));
        $this->assertSame('afternoon', $resolver->advanceDayPart($lead));
    }

    public function test_first_evening_call_schedules_morning_next(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 18:00:00', 'America/New_York'));

        $lead = $this->makeLead();

        $resolver = app(DayPartResolver::class);

        $this->assertTrue($resolver->matchesNextDayPart($lead));
        $this->assertSame('morning', $resolver->advanceDayPart($lead));
    }

    public function test_rotation_cycles_morning_afternoon_evening(): void
    {
        $resolver = app(DayPartResolver::class);
        $lead = $this->makeLead();

        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00', 'America/New_York'));
        $lead->last_attempt_at = now();
        $lead->next_day_part = $resolver->advanceDayPart($lead);
        $this->assertSame('afternoon', $lead->next_day_part);

        Carbon::setTestNow(Carbon::parse('2026-08-11 14:00:00', 'America/New_York'));
        $this->assertTrue($resolver->matchesNextDayPart($lead));
        $lead->last_attempt_at = now();
        $lead->next_day_part = $resolver->advanceDayPart($lead);
        $this->assertSame('evening', $lead->next_day_part);

        Carbon::setTestNow(Carbon::parse('2026-08-12 18:00:00', 'America/New_York'));
        $this->assertTrue($resolver->matchesNextDayPart($lead));
        $lead->last_attempt_at = now();
        $lead->next_day_part = $resolver->advanceDayPart($lead);
        $this->assertSame('morning', $lead->next_day_part);
    }

    public function test_morning_dial_wait_blocks_same_day_afternoon(): void
    {
        $resolver = app(DayPartResolver::class);
        $lead = $this->makeLead();

        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00', 'America/New_York'));
        $lead->last_attempt_at = now();
        $lead->next_day_part = $resolver->advanceDayPart($lead);
        $this->assertSame('afternoon', $lead->next_day_part);

        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/New_York'));
        $this->assertFalse($resolver->matchesNextDayPart($lead));

        Carbon::setTestNow(Carbon::parse('2026-08-11 14:00:00', 'America/New_York'));
        $this->assertTrue($resolver->matchesNextDayPart($lead));
    }

    public function test_blank_wait_allows_same_day_next_window(): void
    {
        $resolver = app(DayPartResolver::class);
        $company = Company::factory()->create();
        $rows = CadenceDefaults::dayPartRows();
        $rows[0]['wait_after_value'] = null;
        $rows[0]['wait_after_unit'] = null;
        $cadence = $this->createCadence($company->id, dayParts: $rows);
        $list = $this->createCallingList($company->id, $cadence);
        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045555678',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => 'callable',
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'imported_at' => now(),
        ])->load('callingList.cadence.dayParts');

        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00', 'America/New_York'));
        $lead->last_attempt_at = now();
        $lead->next_day_part = $resolver->advanceDayPart($lead);

        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/New_York'));
        $this->assertTrue($resolver->matchesNextDayPart($lead));
    }

    public function test_evening_call_schedules_morning_on_next_calendar_day(): void
    {
        $resolver = app(DayPartResolver::class);
        $lead = $this->makeLead();

        Carbon::setTestNow(Carbon::parse('2026-08-10 18:00:00', 'America/New_York'));
        $lead->last_attempt_at = now();
        $lead->next_day_part = $resolver->advanceDayPart($lead);
        $this->assertSame('morning', $lead->next_day_part);

        Carbon::setTestNow(Carbon::parse('2026-08-11 08:30:00', 'America/New_York'));
        $this->assertTrue($resolver->matchesNextDayPart($lead));
    }

    public function test_orphan_next_day_part_allows_any_enabled_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00', 'America/New_York'));

        $lead = $this->makeLead();
        $lead->next_day_part = 'invalid-part';

        $resolver = app(DayPartResolver::class);

        $this->assertTrue($resolver->matchesNextDayPart($lead));
    }

    private function makeLead(): Lead
    {
        $company = Company::factory()->create();
        $cadence = $this->createCadence(
            $company->id,
            dayParts: CadenceDefaults::dayPartRows(),
        );
        $list = $this->createCallingList($company->id, $cadence);

        return Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551234',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => 'callable',
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'imported_at' => now(),
        ])->load('callingList.cadence.dayParts');
    }
}
