<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\PerformanceByLeadSource;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\JasonPaineAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PerformanceByLeadSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_page_loads_with_filters_and_results(): void
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

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551001',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'venue' => 'Grand Hall',
            'event' => 'Spring Showcase',
            'imported_at' => now(),
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $agent->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => ['disposition' => Disposition::Booked->value],
        ]);

        CompanyContext::set($company->id);

        $this->actingAs($admin)->get('/admin/performance-by-lead-source')->assertOk();

        Livewire::actingAs($admin)
            ->test(PerformanceByLeadSource::class)
            ->assertSee('Performance by Lead Source')
            ->assertSee('Start Date')
            ->assertSee('End Date')
            ->assertSee('Grand Hall')
            ->assertSee('Spring Showcase')
            ->assertSee('By Venue and Event');

        Carbon::setTestNow();
    }

    public function test_agent_dashboard_uses_start_and_end_date_labels(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(JasonPaineAdminSeeder::class);

        $user = User::where('email', 'jason.paine@onpointmrg.com')->firstOrFail();
        CompanyContext::set($user->company_id);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Start Date')
            ->assertSee('End Date')
            ->assertDontSee('Run dates');
    }
}
