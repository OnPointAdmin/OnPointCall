<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Resources\LeadHistories\Pages\ListLeadHistories;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class LeadHistoriesTableTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_lead_history_list_shows_activity_columns(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/Los_Angeles')->utc());

        [$company, $admin, $lead] = $this->makeFixtures();

        $history = LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => Carbon::parse('2026-08-10 14:00:00', 'America/Los_Angeles'),
            'payload' => [
                'disposition' => Disposition::LeftVm->value,
                'note' => 'Will call back tomorrow',
            ],
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListLeadHistories::class)
            ->assertOk()
            ->assertSee('When')
            ->assertSee('Event')
            ->assertSee('Actor')
            ->assertSee('Disposition')
            ->assertSee('4045559001')
            ->assertSee('Left VM')
            ->assertSee('Will call back tomorrow')
            ->assertCanSeeTableRecords([$history])
            ->assertDontSee('Create')
            ->assertActionDoesNotExist('create');
    }

    public function test_default_today_filter_hides_yesterday_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/Los_Angeles')->utc());

        [$company, $admin, $lead] = $this->makeFixtures();

        $today = LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => Carbon::parse('2026-08-10 10:00:00', 'America/Los_Angeles'),
            'payload' => ['disposition' => Disposition::NoAnswer->value],
        ]);

        $yesterday = LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Skip,
            'occurred_at' => Carbon::parse('2026-08-09 10:00:00', 'America/Los_Angeles'),
            'payload' => ['skip_reason' => 'Busy'],
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListLeadHistories::class)
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$yesterday]);
    }

    public function test_yesterday_preset_includes_only_yesterday_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/Los_Angeles')->utc());

        [$company, $admin, $lead] = $this->makeFixtures();

        $today = LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => Carbon::parse('2026-08-10 10:00:00', 'America/Los_Angeles'),
            'payload' => ['disposition' => Disposition::NoAnswer->value],
        ]);

        $yesterday = LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Skip,
            'occurred_at' => Carbon::parse('2026-08-09 10:00:00', 'America/Los_Angeles'),
            'payload' => ['skip_reason' => 'Busy'],
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListLeadHistories::class)
            ->filterTable('date_range', [
                'preset' => 'yesterday',
                'start_date' => null,
                'end_date' => null,
            ])
            ->assertCanSeeTableRecords([$yesterday])
            ->assertCanNotSeeTableRecords([$today]);
    }

    public function test_custom_date_range_filters_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/Los_Angeles')->utc());

        [$company, $admin, $lead] = $this->makeFixtures();

        $inRange = LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => Carbon::parse('2026-08-08 10:00:00', 'America/Los_Angeles'),
            'payload' => ['disposition' => Disposition::NoAnswer->value],
        ]);

        $outOfRange = LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => Carbon::parse('2026-08-01 10:00:00', 'America/Los_Angeles'),
            'payload' => ['disposition' => Disposition::LeftVm->value],
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListLeadHistories::class)
            ->filterTable('date_range', [
                'preset' => '',
                'start_date' => '2026-08-07',
                'end_date' => '2026-08-09',
            ])
            ->assertCanSeeTableRecords([$inRange])
            ->assertCanNotSeeTableRecords([$outOfRange]);
    }

    public function test_activity_and_actor_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/Los_Angeles')->utc());

        [$company, $admin, $lead] = $this->makeFixtures();
        $otherAgent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
            'name' => 'Other Agent',
        ]);

        $call = LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => Carbon::parse('2026-08-10 10:00:00', 'America/Los_Angeles'),
            'payload' => ['disposition' => Disposition::NoAnswer->value],
        ]);

        $system = LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => null,
            'event_type' => LeadHistoryType::SoftScore,
            'occurred_at' => Carbon::parse('2026-08-10 11:00:00', 'America/Los_Angeles'),
            'payload' => ['status' => 'clear'],
        ]);

        $otherAgentCall = LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $otherAgent->id,
            'event_type' => LeadHistoryType::Skip,
            'occurred_at' => Carbon::parse('2026-08-10 12:00:00', 'America/Los_Angeles'),
            'payload' => ['skip_reason' => 'Busy'],
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListLeadHistories::class)
            ->filterTable('activity', 'calls')
            ->assertCanSeeTableRecords([$call, $otherAgentCall])
            ->assertCanNotSeeTableRecords([$system])
            ->filterTable('actor_id', $admin->id)
            ->assertCanSeeTableRecords([$call])
            ->assertCanNotSeeTableRecords([$otherAgentCall, $system]);
    }

    public function test_view_action_shows_history_details(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/Los_Angeles')->utc());

        [$company, $admin, $lead] = $this->makeFixtures();

        $history = LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => Carbon::parse('2026-08-10 14:00:00', 'America/Los_Angeles'),
            'payload' => [
                'disposition' => Disposition::Booked->value,
                'note' => 'Booked for Friday',
            ],
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListLeadHistories::class)
            ->mountTableAction('view', $history)
            ->assertSee('Booked for Friday')
            ->assertSee('Booked')
            ->assertSee('4045559001');
    }

    /**
     * @return array{0: Company, 1: User, 2: Lead}
     */
    private function makeFixtures(): array
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
            'name' => 'Admin User',
        ]);

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/Los_Angeles',
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559001',
            'first_name' => 'Pat',
            'last_name' => 'Callahan',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        return [$company, $admin, $lead];
    }
}
