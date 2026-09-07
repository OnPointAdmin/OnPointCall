<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\ReportSchedulePeriod;
use App\Enums\ReportScheduleType;
use App\Enums\UserRole;
use App\Filament\Resources\ReportSchedules\Pages\CreateReportSchedule;
use App\Filament\Resources\ReportSchedules\Pages\EditReportSchedule;
use App\Mail\DashboardDigestMail;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\DashboardEmailRecipient;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\ReportSchedule;
use App\Models\ReportScheduleRecipient;
use App\Models\User;
use App\Services\Dashboard\LegacyDashboardEmailMigrator;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class ReportScheduleTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_command_sends_at_matching_weekday_and_time(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-07 07:00:00', 'America/New_York'));

        $company = $this->makeCompany();
        $schedule = $this->makeSchedule($company);

        $this->artisan('dashboard:email-digest')->assertSuccessful();

        Mail::assertSent(DashboardDigestMail::class, function (DashboardDigestMail $mail) use ($schedule): bool {
            return $mail->hasTo('ops@example.com')
                && str_contains($mail->digestSubject, 'Yesterday')
                && str_contains($mail->htmlBody, 'Agent Dashboard');
        });

        $this->assertSame('2026-09-07 07:00', $schedule->fresh()->last_sent_slot);

        Carbon::setTestNow();
    }

    public function test_command_skips_wrong_time_unless_forced(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-07 08:00:00', 'America/New_York'));

        $company = $this->makeCompany();
        $this->makeSchedule($company);

        $this->artisan('dashboard:email-digest')->assertSuccessful();
        Mail::assertNothingSent();

        $this->artisan('dashboard:email-digest', ['--force' => true])->assertSuccessful();
        Mail::assertSent(DashboardDigestMail::class, 1);

        Carbon::setTestNow();
    }

    public function test_command_skips_wrong_weekday(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-07 07:00:00', 'America/New_York'));

        $company = $this->makeCompany();
        $this->makeSchedule($company, ['days_of_week' => [2]]);

        $this->artisan('dashboard:email-digest')->assertSuccessful();
        Mail::assertNothingSent();

        Carbon::setTestNow();
    }

    public function test_command_does_not_double_send_in_the_same_minute(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-07 07:00:00', 'America/New_York'));

        $company = $this->makeCompany();
        $this->makeSchedule($company);

        $this->artisan('dashboard:email-digest')->assertSuccessful();
        $this->artisan('dashboard:email-digest')->assertSuccessful();

        Mail::assertSent(DashboardDigestMail::class, 1);

        Carbon::setTestNow();
    }

    public function test_today_so_far_includes_today_and_excludes_yesterday(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-07 14:00:00', 'America/New_York'));

        $company = $this->makeCompany();
        $this->makeSchedule($company, [
            'period' => ReportSchedulePeriod::TodaySoFar,
            'send_times' => ['14:00'],
        ]);

        $alice = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'name' => 'Alice Rep',
        ]);
        $list = $this->createCallingList($company->id);
        $todayLead = $this->createLead($company->id, $list->id);
        $yesterdayLead = $this->createLead($company->id, $list->id);

        $this->createDisposition(
            $company->id,
            $todayLead->id,
            $alice->id,
            Disposition::Booked,
            Carbon::parse('2026-09-07 10:00:00', 'America/New_York'),
        );
        $this->createDisposition(
            $company->id,
            $yesterdayLead->id,
            $alice->id,
            Disposition::Booked,
            Carbon::parse('2026-09-06 10:00:00', 'America/New_York'),
        );

        $this->artisan('dashboard:email-digest')->assertSuccessful();

        Mail::assertSent(DashboardDigestMail::class, function (DashboardDigestMail $mail): bool {
            return str_contains($mail->digestSubject, 'Today so far')
                && str_contains($mail->htmlBody, 'Alice Rep')
                && str_contains($mail->htmlBody, 'Sep 7, 2026');
        });

        Carbon::setTestNow();
    }

    public function test_last_week_range_matches_previous_monday_sunday(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-07 07:00:00', 'America/New_York'));

        $company = $this->makeCompany();
        $this->makeSchedule($company, ['period' => ReportSchedulePeriod::LastWeek]);

        $alice = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'name' => 'Alice Rep',
        ]);
        $list = $this->createCallingList($company->id);
        $lastWeekLead = $this->createLead($company->id, $list->id);
        $thisWeekLead = $this->createLead($company->id, $list->id);

        $this->createDisposition(
            $company->id,
            $lastWeekLead->id,
            $alice->id,
            Disposition::Booked,
            Carbon::parse('2026-09-02 10:00:00', 'America/New_York'),
        );
        $this->createDisposition(
            $company->id,
            $thisWeekLead->id,
            $alice->id,
            Disposition::Booked,
            Carbon::parse('2026-09-07 08:00:00', 'America/New_York'),
        );

        $this->artisan('dashboard:email-digest')->assertSuccessful();

        Mail::assertSent(DashboardDigestMail::class, function (DashboardDigestMail $mail): bool {
            return str_contains($mail->digestSubject, 'Last Week')
                && str_contains($mail->digestSubject, 'Aug 31')
                && str_contains($mail->digestSubject, 'Sep 6');
        });

        Carbon::setTestNow();
    }

    public function test_lead_dashboard_and_lead_source_emails_send(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-07 07:00:00', 'America/New_York'));

        $company = $this->makeCompany();
        $this->makeSchedule($company, [
            'name' => 'Lead snapshot',
            'report_type' => ReportScheduleType::LeadDashboard,
            'period' => null,
        ]);
        $this->makeSchedule($company, [
            'name' => 'Lead source',
            'report_type' => ReportScheduleType::LeadSource,
            'period' => ReportSchedulePeriod::Yesterday,
        ], 'source@example.com');

        $this->artisan('dashboard:email-digest')->assertSuccessful();

        Mail::assertSent(DashboardDigestMail::class, 2);
        Mail::assertSent(DashboardDigestMail::class, fn (DashboardDigestMail $mail): bool => str_contains($mail->digestSubject, 'Lead Dashboard'));
        Mail::assertSent(DashboardDigestMail::class, fn (DashboardDigestMail $mail): bool => str_contains($mail->digestSubject, 'Performance by Lead Source'));

        Carbon::setTestNow();
    }

    public function test_legacy_settings_migrate_into_a_default_schedule(): void
    {
        $company = Company::factory()->create();
        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_enabled' => true,
            'dashboard_email_send_time' => '06:30:00',
            'dashboard_email_timezone' => 'America/New_York',
        ]);
        DashboardEmailRecipient::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'email' => 'legacy@example.com',
        ]);

        $this->assertSame(1, app(LegacyDashboardEmailMigrator::class)->migrate());
        $this->assertSame(0, app(LegacyDashboardEmailMigrator::class)->migrate());

        $schedule = ReportSchedule::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $this->assertNotNull($schedule);
        $this->assertSame('Daily Agent Dashboard', $schedule->name);
        $this->assertTrue($schedule->enabled);
        $this->assertSame(ReportScheduleType::AgentDashboard, $schedule->report_type);
        $this->assertSame(ReportSchedulePeriod::Yesterday, $schedule->period);
        $this->assertSame(['06:30'], $schedule->normalizedSendTimes());
        $this->assertSame(['legacy@example.com'], $schedule->recipientEmails());
    }

    public function test_admin_can_create_a_report_schedule(): void
    {
        $admin = $this->makeAdmin();
        CompanyContext::clear();

        Livewire::actingAs($admin)
            ->test(CreateReportSchedule::class)
            ->fillForm([
                'name' => 'Morning leadership',
                'enabled' => true,
                'report_type' => ReportScheduleType::AgentDashboard->value,
                'period' => ReportSchedulePeriod::ThisWeek->value,
                'days_of_week' => [1, 2, 3, 4, 5],
                'send_times' => [
                    ['time' => '07:00'],
                ],
                'recipients' => [
                    ['email' => 'boss@example.com'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $schedule = ReportSchedule::withoutGlobalScopes()
            ->where('company_id', $admin->company_id)
            ->where('name', 'Morning leadership')
            ->first();

        $this->assertNotNull($schedule);
        $this->assertSame(ReportSchedulePeriod::ThisWeek, $schedule->period);
        $this->assertSame([1, 2, 3, 4, 5], $schedule->normalizedDaysOfWeek());
        $this->assertSame(['boss@example.com'], $schedule->recipientEmails());
    }

    public function test_send_now_from_edit_page_ignores_schedule_time(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-07 15:30:00', 'America/New_York'));

        $admin = $this->makeAdmin();
        $company = Company::withoutGlobalScopes()->findOrFail($admin->company_id);
        $schedule = $this->makeSchedule($company, ['send_times' => ['07:00']]);
        CompanyContext::clear();

        Livewire::actingAs($admin)
            ->test(EditReportSchedule::class, ['record' => $schedule->getKey()])
            ->callAction('sendNow');

        Mail::assertSent(DashboardDigestMail::class, 1);

        Carbon::setTestNow();
    }

    private function makeCompany(): Company
    {
        $company = Company::factory()->create();
        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/New_York',
        ]);

        return $company;
    }

    private function makeAdmin(): User
    {
        $company = $this->makeCompany();

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);
    }

    private function makeSchedule(Company $company, array $overrides = [], string $email = 'ops@example.com'): ReportSchedule
    {
        $schedule = ReportSchedule::withoutGlobalScopes()->create(array_merge([
            'company_id' => $company->id,
            'name' => 'Daily Agent Dashboard',
            'enabled' => true,
            'report_type' => ReportScheduleType::AgentDashboard,
            'period' => ReportSchedulePeriod::Yesterday,
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
            'send_times' => ['07:00'],
        ], $overrides));

        ReportScheduleRecipient::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'report_schedule_id' => $schedule->id,
            'email' => $email,
        ]);

        return $schedule->fresh(['recipients']);
    }

    private function createLead(int $companyId, int $callingListId): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => '404555'.random_int(1000, 9999),
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $callingListId,
            'imported_at' => now(),
        ]);
    }

    private function createDisposition(
        int $companyId,
        int $leadId,
        int $actorId,
        Disposition $disposition,
        Carbon $occurredAt,
    ): void {
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => $occurredAt,
            'payload' => ['disposition' => $disposition->value],
        ]);
    }
}
