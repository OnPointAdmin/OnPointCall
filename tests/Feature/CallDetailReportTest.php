<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\ReportSchedulePeriod;
use App\Enums\ReportScheduleType;
use App\Enums\SoftScoreStatus;
use App\Enums\UserRole;
use App\Filament\Pages\CallDetail;
use App\Filament\Resources\ReportSchedules\Pages\CreateReportSchedule;
use App\Mail\DashboardDigestMail;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\ReportSchedule;
use App\Models\ReportScheduleRecipient;
use App\Models\User;
use App\Services\Dashboard\CallDetailReportService;
use App\Services\Dashboard\ManagerDashboardService;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class CallDetailReportTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_report_page_lists_called_leads(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

        [$admin, $agent] = $this->makeUsers();
        $lead = $this->createLead($admin->company_id, [
            'first_name' => 'Pat',
            'last_name' => 'Booked',
            'phone' => '4045551001',
            'venue' => 'Grand Hall',
            'event' => 'Spring Showcase',
        ]);
        $this->createDisposition($admin->company_id, $lead->id, $agent->id, Disposition::Booked);

        CompanyContext::set($admin->company_id);

        $this->actingAs($admin)->get('/admin/call-detail')->assertOk();

        Livewire::actingAs($admin)
            ->test(CallDetail::class)
            ->assertSee('Call Detail')
            ->assertSee('Pat Booked')
            ->assertSee('Booked')
            ->assertSee('Grand Hall')
            ->assertSee('Export CSV')
            ->assertSee('Dispositions')
            ->assertSee('Columns');

        Carbon::setTestNow();
    }

    public function test_csv_includes_call_and_lead_fields(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

        [$admin, $agent] = $this->makeUsers();
        $lead = $this->createLead($admin->company_id, [
            'first_name' => 'Pat',
            'last_name' => 'Booked',
            'phone' => '4045551001',
            'email' => 'pat@example.com',
            'venue' => 'Grand Hall',
        ]);
        $this->createDisposition(
            $admin->company_id,
            $lead->id,
            $agent->id,
            Disposition::Booked,
            ['note' => 'VIP party'],
        );

        $range = app(ManagerDashboardService::class)->todayRange($admin->company_id);
        $csv = app(CallDetailReportService::class)->toCsv(
            $admin->company_id,
            [],
            $range['start'],
            $range['end'],
        );

        $this->assertStringContainsString('Called At', $csv);
        $this->assertStringContainsString('Pat', $csv);
        $this->assertStringContainsString('Booked', $csv);
        $this->assertStringContainsString('VIP party', $csv);
        $this->assertStringContainsString('pat@example.com', $csv);
        $this->assertStringContainsString($agent->name, $csv);

        Carbon::setTestNow();
    }

    public function test_csv_can_include_qualified_partners_demographics_and_soft_score(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

        [$admin, $agent] = $this->makeUsers();
        $lead = $this->createLead($admin->company_id, [
            'first_name' => 'Pat',
            'last_name' => 'Booked',
            'age_range' => '45-54',
            'annual_income' => '$100,000-$149,999',
            'marital_status' => 'Married',
            'gender' => 'Female',
            'home_owner' => 'Yes',
            'soft_score_code' => 'A',
            'soft_score_status' => SoftScoreStatus::Complete,
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_result' => [
                'request' => ['surveyCompanyId' => 'test'],
                'response' => [
                    'qualifiedCompaniesBooking' => [
                        ['companyName' => 'Travel Partner', 'vertical' => 'Vacation'],
                    ],
                ],
            ],
        ]);
        $this->createDisposition($admin->company_id, $lead->id, $agent->id, Disposition::Booked);

        $range = app(ManagerDashboardService::class)->todayRange($admin->company_id);
        $csv = app(CallDetailReportService::class)->toCsv(
            $admin->company_id,
            ['columns' => ['name', 'soft_score', 'qualified_partners', 'age_range', 'annual_income']],
            $range['start'],
            $range['end'],
        );

        $this->assertStringContainsString('Soft Score', $csv);
        $this->assertStringContainsString('Qualified Partners', $csv);
        $this->assertStringContainsString('Age range', $csv);
        $this->assertStringContainsString('A', $csv);
        $this->assertStringContainsString('Travel Partner', $csv);
        $this->assertStringContainsString('45-54', $csv);
        $this->assertStringContainsString('$100,000-$149,999', $csv);
        $this->assertStringNotContainsString('Called At', $csv);

        Carbon::setTestNow();
    }

    public function test_report_page_can_toggle_optional_columns(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

        [$admin, $agent] = $this->makeUsers();
        $lead = $this->createLead($admin->company_id, [
            'first_name' => 'Pat',
            'last_name' => 'Booked',
            'age_range' => '45-54',
            'soft_score_code' => 'B2',
            'qualification_result' => [
                'response' => [
                    'qualifiedCompaniesBooking' => [
                        ['companyName' => 'Travel Partner'],
                    ],
                ],
            ],
        ]);
        $this->createDisposition($admin->company_id, $lead->id, $agent->id, Disposition::Booked);

        CompanyContext::set($admin->company_id);

        Livewire::actingAs($admin)
            ->test(CallDetail::class)
            ->assertDontSee('Travel Partner')
            ->assertDontSee('45-54')
            ->assertDontSee('B2')
            ->set('visibleColumns', ['name', 'qualified_partners', 'age_range', 'soft_score'])
            ->assertSee('Travel Partner')
            ->assertSee('45-54')
            ->assertSee('B2')
            ->call('resetColumns')
            ->assertDontSee('Travel Partner')
            ->assertDontSee('B2');

        Carbon::setTestNow();
    }

    public function test_disposition_filter_excludes_other_outcomes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

        [$admin, $agent] = $this->makeUsers();
        $booked = $this->createLead($admin->company_id, ['first_name' => 'Zelda', 'last_name' => 'Win']);
        $ni = $this->createLead($admin->company_id, ['first_name' => 'Nora', 'last_name' => 'Nope']);
        $this->createDisposition($admin->company_id, $booked->id, $agent->id, Disposition::Booked);
        $this->createDisposition($admin->company_id, $ni->id, $agent->id, Disposition::NotInterested);

        $range = app(ManagerDashboardService::class)->todayRange($admin->company_id);
        $csv = app(CallDetailReportService::class)->toCsv(
            $admin->company_id,
            ['dispositions' => [Disposition::Booked->value]],
            $range['start'],
            $range['end'],
        );

        $this->assertStringContainsString('Zelda', $csv);
        $this->assertStringNotContainsString('Nora', $csv);

        Carbon::setTestNow();
    }

    public function test_skip_filter_includes_skip_events(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

        [$admin, $agent] = $this->makeUsers();
        $skipped = $this->createLead($admin->company_id, ['first_name' => 'Skippy', 'last_name' => 'Lane']);
        $booked = $this->createLead($admin->company_id, ['first_name' => 'Zelda', 'last_name' => 'Win']);
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $admin->company_id,
            'lead_id' => $skipped->id,
            'actor_id' => $agent->id,
            'event_type' => LeadHistoryType::Skip,
            'occurred_at' => now(),
            'payload' => ['disposition' => Disposition::Skip->value, 'reason' => 'Already talking'],
        ]);
        $this->createDisposition($admin->company_id, $booked->id, $agent->id, Disposition::Booked);

        $range = app(ManagerDashboardService::class)->todayRange($admin->company_id);
        $csv = app(CallDetailReportService::class)->toCsv(
            $admin->company_id,
            ['dispositions' => [Disposition::Skip->value]],
            $range['start'],
            $range['end'],
        );

        $this->assertStringContainsString('Skippy', $csv);
        $this->assertStringContainsString('Already talking', $csv);
        $this->assertStringNotContainsString('Zelda', $csv);

        Carbon::setTestNow();
    }

    public function test_scheduled_call_detail_email_attaches_csv(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-07 07:00:00', 'America/New_York'));

        [$admin, $agent] = $this->makeUsers();
        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $admin->company_id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/New_York',
        ]);

        $lead = $this->createLead($admin->company_id, [
            'first_name' => 'Pat',
            'last_name' => 'Yesterday',
        ]);
        $this->createDisposition(
            $admin->company_id,
            $lead->id,
            $agent->id,
            Disposition::Booked,
            occurredAt: Carbon::parse('2026-09-06 14:00:00', 'America/New_York')->utc(),
        );

        $schedule = ReportSchedule::withoutGlobalScopes()->create([
            'company_id' => $admin->company_id,
            'name' => 'Daily call detail',
            'enabled' => true,
            'report_type' => ReportScheduleType::CallDetail,
            'period' => ReportSchedulePeriod::Yesterday,
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
            'send_times' => ['07:00'],
            'filters' => ['dispositions' => [Disposition::Booked->value]],
        ]);
        ReportScheduleRecipient::withoutGlobalScopes()->create([
            'company_id' => $admin->company_id,
            'report_schedule_id' => $schedule->id,
            'email' => 'ops@example.com',
        ]);

        $this->artisan('dashboard:email-digest')->assertSuccessful();

        Mail::assertSent(DashboardDigestMail::class, function (DashboardDigestMail $mail): bool {
            return str_contains($mail->digestSubject, 'Call Detail')
                && ($mail->fileAttachments[0]['filename'] ?? '') === 'call-detail-2026-09-06-to-2026-09-06.csv'
                && str_contains($mail->fileAttachments[0]['content'] ?? '', 'Pat')
                && str_contains($mail->fileAttachments[0]['content'] ?? '', 'Booked');
        });

        Carbon::setTestNow();
    }

    public function test_schedule_form_shows_filters_for_call_detail(): void
    {
        [$admin] = $this->makeUsers();
        CompanyContext::clear();

        Livewire::actingAs($admin)
            ->test(CreateReportSchedule::class)
            ->assertFormFieldIsHidden('filters.agent_id')
            ->fillForm([
                'report_type' => ReportScheduleType::CallDetail->value,
            ])
            ->assertFormFieldIsVisible('filters.agent_id')
            ->assertFormFieldIsVisible('filters.dispositions')
            ->assertFormFieldIsVisible('filters.columns')
            ->assertFormFieldIsVisible('period');
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function makeUsers(): array
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
            'name' => 'Alice Rep',
        ]);

        return [$admin, $agent];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLead(int $companyId, array $overrides = []): Lead
    {
        return Lead::withoutGlobalScopes()->create(array_merge([
            'company_id' => $companyId,
            'phone' => '404555'.random_int(1000, 9999),
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createDisposition(
        int $companyId,
        int $leadId,
        int $actorId,
        Disposition $disposition,
        array $payload = [],
        ?Carbon $occurredAt = null,
    ): void {
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => $occurredAt ?? now(),
            'payload' => array_merge(['disposition' => $disposition->value], $payload),
        ]);
    }
}
