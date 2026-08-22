<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Pages\LeadDashboard;
use App\Models\Company;
use App\Models\Lead;
use App\Models\StateRule;
use App\Models\User;
use App\Services\Dashboard\LeadDashboardService;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class LeadDashboardTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));
    }

    public function test_lead_dashboard_page_loads(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        CompanyContext::set($company->id);

        $this->actingAs($admin)->get('/admin/lead-dashboard')->assertOk();

        Livewire::actingAs($admin)
            ->test(LeadDashboard::class)
            ->assertSee('Lead Dashboard')
            ->assertSee('When leads become dialable')
            ->assertSee('Ready now')
            ->assertSee('By calling list');
    }

    public function test_snapshot_splits_ready_now_waiting_and_status_counts(): void
    {
        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id, $this->createCadenceWithDayParts($company->id));

        $this->makeLead($company->id, $list->id, LeadStatus::Callable, '4045551001');
        $this->makeLead(
            $company->id,
            $list->id,
            LeadStatus::Callable,
            '4045551002',
            attemptCount: 1,
            lastAttemptAt: now()->subMinutes(5),
        );
        $this->makeLead($company->id, $list->id, LeadStatus::Holding, '4045551003');
        $this->makeLead($company->id, $list->id, LeadStatus::Booked, '4045551004');

        $snapshot = app(LeadDashboardService::class)->snapshot($company->id);

        $this->assertSame(4, $snapshot->total);
        $this->assertSame(1, $snapshot->fresh);
        $this->assertSame(1, $snapshot->statusCounts['holding']);
        $this->assertSame(2, $snapshot->statusCounts['callable']);
        $this->assertSame(1, $snapshot->statusCounts['booked']);
        $this->assertSame(1, $snapshot->readyNow);
        $this->assertSame(1, $snapshot->waiting);
        $this->assertSame(1, $this->forecastCount($snapshot->forecast, 'ready_now'));
        $this->assertSame(1, $this->forecastCount($snapshot->forecast, 'next_hour'));
        $this->assertSame('Test List', $snapshot->byList[0]['name']);
        $this->assertSame(1, $snapshot->byList[0]['ready_now']);
        $this->assertSame(1, $snapshot->byList[0]['waiting']);
        $this->assertSame(1, $snapshot->byList[0]['holding']);
    }

    public function test_after_hours_leads_forecast_as_tomorrow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 22:30:00', 'America/New_York'));

        $company = $this->makeCompany();
        $list = $this->createCallingList($company->id);
        $this->makeLead($company->id, $list->id, LeadStatus::Callable, '4045551001');

        $snapshot = app(LeadDashboardService::class)->snapshot($company->id);

        $this->assertSame(0, $snapshot->readyNow);
        $this->assertSame(1, $snapshot->waiting);
        $this->assertSame(1, $this->forecastCount($snapshot->forecast, 'tomorrow'));
    }

    public function test_calendar_gap_forecasts_within_next_seven_days(): void
    {
        $company = $this->makeCompany();
        $cadence = $this->createCadence(
            $company->id,
            name: 'Seven day',
            attemptGaps: [
                ['after_attempt' => 4, 'wait_value' => 7, 'wait_unit' => 'days'],
            ],
        );
        $list = $this->createCallingList($company->id, $cadence);
        $this->makeLead(
            $company->id,
            $list->id,
            LeadStatus::Callable,
            '4045551001',
            attemptCount: 4,
            lastAttemptAt: Carbon::parse('2026-08-10 14:00:00', 'America/New_York'),
        );

        $snapshot = app(LeadDashboardService::class)->snapshot($company->id);

        $this->assertSame(0, $snapshot->readyNow);
        $this->assertSame(1, $snapshot->waiting);
        $this->assertSame(1, $this->forecastCount($snapshot->forecast, 'next_7_days'));
    }

    /**
     * @param  list<array{key: string, label: string, count: int}>  $forecast
     */
    private function forecastCount(array $forecast, string $key): int
    {
        foreach ($forecast as $bucket) {
            if ($bucket['key'] === $key) {
                return $bucket['count'];
            }
        }

        return 0;
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
