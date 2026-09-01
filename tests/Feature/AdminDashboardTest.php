<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\StateRule;
use App\Models\User;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\JasonPaineAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_admin_dashboard_loads_after_login(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(JasonPaineAdminSeeder::class);

        $user = User::where('email', 'jason.paine@onpointmrg.com')->firstOrFail();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
        $response->assertSee('Totals');
        $response->assertSee('Results by Rep');
        $response->assertSee('Agent Dashboard');
    }

    public function test_dashboard_page_renders_report_sections(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(JasonPaineAdminSeeder::class);

        $user = User::where('email', 'jason.paine@onpointmrg.com')->firstOrFail();
        CompanyContext::set($user->company_id);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSet('report', fn (?array $report): bool => is_array($report) && isset($report['totals'], $report['agents']))
            ->assertSee('Total Leads Called')
            ->assertSee('No Answer / VM')
            ->assertSee('Wrong / DNC')
            ->assertSee('Calling list');
    }

    public function test_dashboard_shows_queue_status_for_active_lists_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

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
        ]);

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/New_York',
        ]);

        $activeList = $this->createCallingList($company->id, overrides: ['name' => 'Active List']);
        $idleList = $this->createCallingList($company->id, overrides: ['name' => 'Idle List']);

        $activeLead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559201',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $activeList->id,
            'imported_at' => now(),
        ]);
        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559202',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $idleList->id,
            'imported_at' => now(),
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $activeLead->id,
            'actor_id' => $agent->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => ['disposition' => Disposition::NoAnswer->value],
        ]);

        CompanyContext::set($company->id);

        $component = Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertSee('Queue status')
            ->assertSee('Active List')
            ->assertSee('Ready now');

        $listNames = collect($component->instance()->queueStatuses())
            ->pluck('list.name')
            ->all();

        $this->assertSame(['Active List'], $listNames);

        Carbon::setTestNow();
    }

    public function test_dashboard_queue_status_shows_evening_cadence_timing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

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
        ]);

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/New_York',
        ]);

        StateRule::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'state_code' => 'NY',
            'window_start' => '08:00:00',
            'window_end' => '21:00:00',
            'permitted_weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'manual_dial_only' => false,
        ]);

        $list = $this->createCallingList(
            $company->id,
            $this->createCadenceWithDayParts($company->id),
            ['name' => 'Standard - List A'],
        );
        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559301',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'attempt_count' => 1,
            'last_attempt_at' => now()->subMinutes(5),
            'next_day_part' => 'evening',
            'imported_at' => now(),
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $agent->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => ['disposition' => Disposition::NoAnswer->value],
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertSee('Queue status')
            ->assertSee('Standard - List A')
            ->assertSee('Evening')
            ->assertSee('Tomorrow');

        Carbon::setTestNow();
    }

    public function test_date_preset_fills_run_dates_and_applies(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(JasonPaineAdminSeeder::class);

        $user = User::where('email', 'jason.paine@onpointmrg.com')->firstOrFail();
        CompanyContext::set($user->company_id);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSet('datePreset', 'today')
            ->assertSee('This Week')
            ->assertSee('Last Week')
            ->assertSee('MTD')
            ->assertSee('YTD')
            ->call('applyPreset', 'mtd')
            ->assertSet('datePreset', 'mtd');
    }

    public function test_session_user_lookup_does_not_recurse_through_company_scope(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(JasonPaineAdminSeeder::class);

        $user = User::withoutGlobalScopes()
            ->where('email', 'jason.paine@onpointmrg.com')
            ->firstOrFail();

        CompanyContext::clear();

        $retrieved = $this->app['auth']->guard()->getProvider()->retrieveById($user->id);

        $this->assertNotNull($retrieved);
        $this->assertSame($user->id, $retrieved->id);

        $this->actingAs($retrieved)->get('/admin')->assertOk();
    }

    public function test_today_preset_counts_company_local_calls_not_utc_yesterday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 15:00:00', 'America/New_York'));

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
            'name' => 'Iranays Ferro',
        ]);

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/New_York',
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559001',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $agent->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => ['disposition' => Disposition::NoAnswer->value],
        ]);
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $agent->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now()->subDay(),
            'payload' => ['disposition' => Disposition::NotInterested->value],
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertSet('datePreset', 'today')
            ->assertSet('report.totals.total_leads_called.count', 1)
            ->assertSet('report.totals.no_answer_vm.count', 1)
            ->assertSet('report.totals.not_interested.count', 0)
            ->assertSee('Iranays Ferro');

        Carbon::setTestNow();
    }

    public function test_dashboard_filters_totals_by_calling_list(): void
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
        ]);

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/New_York',
        ]);

        $standardList = $this->createCallingList($company->id, overrides: ['name' => 'Standard']);
        $tnbList = $this->createCallingList($company->id, overrides: ['name' => 'TNB']);

        $standardLead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559101',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $standardList->id,
            'imported_at' => now(),
        ]);
        $tnbLead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559102',
            'status' => LeadStatus::Callable,
            'lead_type' => 'tnb',
            'calling_list_id' => $tnbList->id,
            'imported_at' => now(),
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $standardLead->id,
            'actor_id' => $agent->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => ['disposition' => Disposition::Booked->value],
        ]);
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $tnbLead->id,
            'actor_id' => $agent->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => ['disposition' => Disposition::NotInterested->value],
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertSet('report.totals.total_leads_called.count', 2)
            ->fillForm([
                'calling_list_id' => $standardList->id,
            ], 'filterForm')
            ->call('applyFiltersAction')
            ->assertSet('report.totals.total_leads_called.count', 1)
            ->assertSet('report.totals.booked.count', 1)
            ->assertSet('report.totals.not_interested.count', 0)
            ->assertSee('Calling list')
            ->assertSee('Standard');
    }
}
