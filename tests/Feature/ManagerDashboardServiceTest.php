<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Services\Dashboard\ManagerDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_bundles_dispositions_and_counts_total_leads_called(): void
    {
        $company = Company::factory()->create();
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        $lead = $this->createLead($company->id, 'standard');
        $otherLead = $this->createLead($company->id, 'standard');

        $this->createDisposition($company->id, $lead->id, $agent->id, Disposition::Booked);
        $this->createDisposition($company->id, $lead->id, $agent->id, Disposition::NotInterested);
        $this->createDisposition($company->id, $lead->id, $agent->id, Disposition::NotQualified);
        $this->createDisposition($company->id, $lead->id, $agent->id, Disposition::NoAnswer);
        $this->createDisposition($company->id, $lead->id, $agent->id, Disposition::LeftVm);
        $this->createDisposition($company->id, $lead->id, $agent->id, Disposition::WrongNumber);
        $this->createDisposition($company->id, $otherLead->id, $agent->id, Disposition::BadNumber);
        $this->createDisposition($company->id, $otherLead->id, $agent->id, Disposition::Dnc);
        $this->createDisposition($company->id, $otherLead->id, $agent->id, Disposition::Callback);
        $this->createSkip($company->id, $otherLead->id, $agent->id);

        $service = app(ManagerDashboardService::class);
        $range = $service->todayRange($company->id);
        $report = $service->report($company->id, null, null, $range['start'], $range['end']);

        $totals = $report['totals'];

        $this->assertSame(10, $totals['total_leads_called']['count']);
        $this->assertSame(1, $totals['booked']['count']);
        $this->assertSame(1, $totals['not_interested']['count']);
        $this->assertSame(1, $totals['not_qualified']['count']);
        $this->assertSame(2, $totals['no_answer_vm']['count']);
        $this->assertSame(3, $totals['wrong_dnc']['count']);
        $this->assertSame(1, $totals['skipped']['count']);
        $this->assertSame(1, $totals['callbacks']['count']);
        $this->assertSame(10.0, $totals['booked']['percent']);
    }

    public function test_report_filters_by_agent(): void
    {
        $company = Company::factory()->create();
        $agentOne = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'name' => 'Agent One',
        ]);
        $agentTwo = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'name' => 'Agent Two',
        ]);

        $lead = $this->createLead($company->id, 'standard');

        $this->createDisposition($company->id, $lead->id, $agentOne->id, Disposition::Booked);
        $this->createDisposition($company->id, $lead->id, $agentTwo->id, Disposition::NotInterested);

        $service = app(ManagerDashboardService::class);
        $range = $service->todayRange($company->id);
        $report = $service->report($company->id, $agentOne->id, null, $range['start'], $range['end']);

        $this->assertSame(1, $report['totals']['total_leads_called']['count']);
        $this->assertSame(1, $report['totals']['booked']['count']);
        $this->assertSame(0, $report['totals']['not_interested']['count']);
        $this->assertCount(1, $report['agents']);
        $this->assertSame('Agent One', $report['agents'][0]['name']);
    }

    public function test_report_filters_by_lead_type(): void
    {
        $company = Company::factory()->create();
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        $standardLead = $this->createLead($company->id, 'standard');
        $tnbLead = $this->createLead($company->id, 'tnb');

        $this->createDisposition($company->id, $standardLead->id, $agent->id, Disposition::Booked);
        $this->createDisposition($company->id, $tnbLead->id, $agent->id, Disposition::NotInterested);

        $service = app(ManagerDashboardService::class);
        $range = $service->todayRange($company->id);
        $report = $service->report($company->id, null, 'standard', $range['start'], $range['end']);

        $this->assertSame(1, $report['totals']['total_leads_called']['count']);
        $this->assertSame(1, $report['totals']['booked']['count']);
        $this->assertSame(0, $report['totals']['not_interested']['count']);
    }

    public function test_overdue_callbacks_use_live_snapshot_not_history_range(): void
    {
        $company = Company::factory()->create();
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559001',
            'status' => LeadStatus::Callback,
            'lead_type' => 'standard',
            'callback_owner_id' => $agent->id,
            'callback_at' => now()->subDay(),
            'imported_at' => now(),
        ]);

        $service = app(ManagerDashboardService::class);
        $range = $service->dateRange(
            $company->id,
            Carbon::now()->subDays(30),
            Carbon::now()->subDays(20),
        );

        $report = $service->report($company->id, null, null, $range['start'], $range['end']);

        $this->assertSame(0, $report['totals']['total_leads_called']['count']);
        $this->assertSame(1, $report['totals']['overdue_callbacks']['count']);
        $this->assertSame(1, $report['agents'][0]['metrics']['overdue_callbacks']['count']);
    }

    public function test_date_presets_use_monday_sunday_weeks(): void
    {
        $service = app(ManagerDashboardService::class);
        $now = Carbon::parse('2026-08-19 15:00:00', 'America/New_York'); // Wednesday

        $today = $service->presetDates('today', 'America/New_York', $now);
        $this->assertSame('2026-08-19', $today['start']->toDateString());
        $this->assertSame('2026-08-19', $today['end']->toDateString());

        $yesterday = $service->presetDates('yesterday', 'America/New_York', $now);
        $this->assertSame('2026-08-18', $yesterday['start']->toDateString());
        $this->assertSame('2026-08-18', $yesterday['end']->toDateString());

        $thisWeek = $service->presetDates('this_week', 'America/New_York', $now);
        $this->assertSame('2026-08-17', $thisWeek['start']->toDateString());
        $this->assertSame('2026-08-19', $thisWeek['end']->toDateString());

        $lastWeek = $service->presetDates('last_week', 'America/New_York', $now);
        $this->assertSame('2026-08-10', $lastWeek['start']->toDateString());
        $this->assertSame('2026-08-16', $lastWeek['end']->toDateString());

        $mtd = $service->presetDates('mtd', 'America/New_York', $now);
        $this->assertSame('2026-08-01', $mtd['start']->toDateString());
        $this->assertSame('2026-08-19', $mtd['end']->toDateString());

        $ytd = $service->presetDates('ytd', 'America/New_York', $now);
        $this->assertSame('2026-01-01', $ytd['start']->toDateString());
        $this->assertSame('2026-08-19', $ytd['end']->toDateString());
    }

    public function test_date_range_treats_utc_parsed_picker_dates_as_company_local_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 15:00:00', 'America/New_York'));

        $company = Company::factory()->create();
        $this->createSettings($company->id);

        $service = app(ManagerDashboardService::class);
        $range = $service->dateRange(
            $company->id,
            Carbon::parse('2026-08-26'),
            Carbon::parse('2026-08-26'),
        );

        $this->assertTrue(
            $range['start']->equalTo(Carbon::parse('2026-08-26 00:00:00', 'America/New_York')->startOfDay()->utc()),
            $range['start']->toIso8601String(),
        );
        $this->assertTrue(
            $range['end']->equalTo(Carbon::parse('2026-08-26 00:00:00', 'America/New_York')->endOfDay()->utc()),
            $range['end']->toIso8601String(),
        );

        Carbon::setTestNow();
    }

    public function test_report_for_picker_today_counts_company_local_today_not_utc_yesterday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 15:00:00', 'America/New_York'));

        $company = Company::factory()->create();
        $this->createSettings($company->id);
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);
        $lead = $this->createLead($company->id, 'standard');

        $this->createDisposition($company->id, $lead->id, $agent->id, Disposition::NoAnswer, now());
        $this->createDisposition(
            $company->id,
            $lead->id,
            $agent->id,
            Disposition::NotInterested,
            now()->subDay(),
        );

        $service = app(ManagerDashboardService::class);
        $range = $service->dateRange(
            $company->id,
            Carbon::parse('2026-08-26'),
            Carbon::parse('2026-08-26'),
        );
        $report = $service->report($company->id, null, null, $range['start'], $range['end']);

        $this->assertSame(1, $report['totals']['total_leads_called']['count']);
        $this->assertSame(1, $report['totals']['no_answer_vm']['count']);
        $this->assertSame(0, $report['totals']['not_interested']['count']);

        Carbon::setTestNow();
    }

    private function createLead(int $companyId, string $leadType): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => '404555'.random_int(1000, 9999),
            'status' => LeadStatus::Callable,
            'lead_type' => $leadType,
            'imported_at' => now(),
        ]);
    }

    private function createSettings(int $companyId): void
    {
        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/New_York',
        ]);
    }

    private function createDisposition(
        int $companyId,
        int $leadId,
        int $actorId,
        Disposition $disposition,
        ?Carbon $occurredAt = null,
    ): void {
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => $occurredAt ?? now(),
            'payload' => ['disposition' => $disposition->value],
        ]);
    }

    private function createSkip(int $companyId, int $leadId, int $actorId): void
    {
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'event_type' => LeadHistoryType::Skip,
            'occurred_at' => now(),
            'payload' => [],
        ]);
    }
}
