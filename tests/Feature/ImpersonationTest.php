<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\ListAssignment;
use App\Models\User;
use App\Services\Auth\Impersonation;
use App\Support\CompanyContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_admin_can_start_impersonation_from_users_table(): void
    {
        [$company, $admin, $agent] = $this->createAdminAndAgent();

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableActionVisible('logInAs', $agent)
            ->callTableAction('logInAs', $agent);

        $this->assertSame($admin->id, session(Impersonation::SESSION_KEY));
        $this->assertAuthenticatedAs($agent, 'agent');
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_workspace_shows_impersonation_banner(): void
    {
        [$company, $admin, $agent] = $this->createAdminAndAgent();

        app(Impersonation::class)->start($admin, $agent);

        $this->actingAs($agent, 'agent')
            ->get(route('agent.workspace'))
            ->assertOk()
            ->assertSee('Viewing as')
            ->assertSee($agent->name)
            ->assertSee('Return to my account')
            ->assertDontSee('href="'.url('/admin').'"', false);
    }

    public function test_stop_impersonation_restores_admin_on_agent_guard(): void
    {
        [$company, $admin, $agent] = $this->createAdminAndAgent();

        app(Impersonation::class)->start($admin, $agent);

        $this->actingAs($agent, 'agent')
            ->withSession([Impersonation::SESSION_KEY => $admin->id])
            ->post(route('agent.impersonation.stop'))
            ->assertRedirect('/admin/users');

        $this->assertFalse(session()->has(Impersonation::SESSION_KEY));
        $this->assertAuthenticatedAs($admin, 'agent');
    }

    public function test_manager_cannot_see_log_in_as_action(): void
    {
        [$company, , $agent] = $this->createAdminAndAgent();

        $manager = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Manager,
            'active' => true,
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($manager)
            ->test(ListUsers::class)
            ->assertTableActionHidden('logInAs', $agent);
    }

    public function test_manager_cannot_start_impersonation(): void
    {
        [$company, , $agent] = $this->createAdminAndAgent();

        $manager = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Manager,
            'active' => true,
        ]);

        $this->expectException(AuthorizationException::class);

        app(Impersonation::class)->start($manager, $agent);
    }

    public function test_admin_cannot_log_in_as_self(): void
    {
        [$company, $admin] = $this->createAdminAndAgent();

        $this->expectException(AuthorizationException::class);

        app(Impersonation::class)->start($admin, $admin);
    }

    public function test_admin_cannot_log_in_as_inactive_user(): void
    {
        [$company, $admin, $agent] = $this->createAdminAndAgent();

        $agent->forceFill(['active' => false])->save();

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableActionHidden('logInAs', $agent);

        $this->expectException(AuthorizationException::class);

        app(Impersonation::class)->start($admin, $agent->fresh());
    }

    public function test_admin_cannot_log_in_as_user_in_another_company(): void
    {
        [$company, $admin] = $this->createAdminAndAgent();

        $otherCompany = Company::factory()->create();
        $otherAgent = User::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => UserRole::Agent,
            'active' => true,
        ]);

        $this->expectException(AuthorizationException::class);

        app(Impersonation::class)->start($admin, $otherAgent);
    }

    public function test_impersonating_user_with_required_password_change_can_access_workspace(): void
    {
        [$company, $admin, $agent] = $this->createAdminAndAgent();

        $agent->forceFill(['must_change_password' => true])->save();

        app(Impersonation::class)->start($admin, $agent->fresh());

        $this->actingAs($agent, 'agent')
            ->withSession([Impersonation::SESSION_KEY => $admin->id])
            ->get(route('agent.workspace'))
            ->assertOk()
            ->assertSee('Viewing as');
    }

    public function test_impersonating_agent_without_lists_can_access_workspace(): void
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

        app(Impersonation::class)->start($admin, $agent);

        $this->actingAs($agent, 'agent')
            ->withSession([Impersonation::SESSION_KEY => $admin->id])
            ->get(route('agent.workspace'))
            ->assertOk()
            ->assertSee('Return to my account');
    }

    public function test_impersonating_manager_does_not_replace_web_guard(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        $manager = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Manager,
            'active' => true,
        ]);

        Auth::guard('web')->login($admin);

        app(Impersonation::class)->start($admin, $manager);

        $this->assertAuthenticatedAs($manager, 'agent');
        $this->assertAuthenticatedAs($admin, 'web');

        $this->withSession([Impersonation::SESSION_KEY => $admin->id])
            ->actingAs($admin, 'web')
            ->get('/admin/users')
            ->assertOk();

        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_agent_logout_while_impersonating_returns_to_users_list(): void
    {
        [$company, $admin, $agent] = $this->createAdminAndAgent();

        app(Impersonation::class)->start($admin, $agent);

        $this->actingAs($agent, 'agent')
            ->withSession([Impersonation::SESSION_KEY => $admin->id])
            ->post(route('agent.logout'))
            ->assertRedirect('/admin/users');

        $this->assertFalse(session()->has(Impersonation::SESSION_KEY));
        $this->assertAuthenticatedAs($admin, 'agent');
    }

    /**
     * @return array{0: Company, 1: User, 2: User}
     */
    private function createAdminAndAgent(): array
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

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'lead_type' => 'standard',
            'active' => true,
        ]);

        ListAssignment::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'calling_list_id' => $list->id,
        ]);

        return [$company, $admin, $agent];
    }
}
