<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\ListAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_agent_can_login_and_access_workspace(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'password' => bcrypt('password'),
        ]);

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'lead_type' => 'standard',
            'active' => true,
        ]);

        ListAssignment::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'calling_list_id' => $list->id,
        ]);

        $response = $this->post('/agent/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('agent.workspace'));
        $this->assertAuthenticatedAs($user, 'agent');

        $this->get(route('agent.workspace'))
            ->assertOk()
            ->assertSee('Get Next Lead');
    }

    public function test_agent_and_admin_sessions_can_coexist(): void
    {
        $company = Company::factory()->create();

        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'password' => bcrypt('password'),
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'password' => bcrypt('password'),
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

        $this->post('/agent/login', [
            'email' => $agent->email,
            'password' => 'password',
        ])->assertRedirect(route('agent.workspace'));

        $this->actingAs($admin, 'web');

        $this->assertAuthenticatedAs($agent, 'agent');
        $this->assertAuthenticatedAs($admin, 'web');

        $this->get(route('agent.workspace'))->assertOk();
        $this->get('/admin')->assertOk();
    }

    public function test_agent_logout_does_not_clear_admin_session(): void
    {
        $company = Company::factory()->create();

        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'password' => bcrypt('password'),
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'password' => bcrypt('password'),
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

        $this->post('/agent/login', [
            'email' => $agent->email,
            'password' => 'password',
        ])->assertRedirect(route('agent.workspace'));

        $this->actingAs($admin, 'web');

        $this->post(route('agent.logout'))
            ->assertRedirect(route('agent.login'));

        $this->assertGuest('agent');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->get('/admin')->assertOk();
    }

    public function test_admin_logout_does_not_clear_agent_session(): void
    {
        $company = Company::factory()->create();

        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'password' => bcrypt('password'),
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'password' => bcrypt('password'),
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

        $this->post('/agent/login', [
            'email' => $agent->email,
            'password' => 'password',
        ])->assertRedirect(route('agent.workspace'));

        $this->actingAs($admin, 'web');

        $this->post('/admin/logout')
            ->assertRedirect();

        $this->assertGuest('web');
        $this->assertAuthenticatedAs($agent, 'agent');
        $this->get(route('agent.workspace'))->assertOk();
    }

    public function test_unauthenticated_agent_workspace_redirects_to_login(): void
    {
        $this->get(route('agent.workspace'))
            ->assertRedirect(route('agent.login'));
    }

    public function test_user_without_list_assignment_cannot_login(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/agent/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('agent');
    }
}
