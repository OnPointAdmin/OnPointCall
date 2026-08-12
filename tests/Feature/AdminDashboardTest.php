<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CompanyContext;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\JasonPaineAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_loads_after_login(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(JasonPaineAdminSeeder::class);

        $user = User::where('email', 'jason.paine@onpointcall.com')->firstOrFail();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    public function test_session_user_lookup_does_not_recurse_through_company_scope(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(JasonPaineAdminSeeder::class);

        $user = User::withoutGlobalScopes()
            ->where('email', 'jason.paine@onpointcall.com')
            ->firstOrFail();

        CompanyContext::clear();

        $retrieved = $this->app['auth']->guard()->getProvider()->retrieveById($user->id);

        $this->assertNotNull($retrieved);
        $this->assertSame($user->id, $retrieved->id);

        $this->actingAs($retrieved)->get('/admin')->assertOk();
    }
}
