<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Support\CompanyContext;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\JasonPaineAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_loads_after_login(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(JasonPaineAdminSeeder::class);

        $user = User::where('email', 'jason.paine@onpointmrg.com')->firstOrFail();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
        $response->assertSee('Totals');
        $response->assertSee('Results by Rep');
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
            ->assertSee('Wrong / DNC');
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
}
