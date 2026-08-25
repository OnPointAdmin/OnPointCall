<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\ListAssignment;
use App\Models\User;
use Filament\Auth\Pages\Login as FilamentLogin;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_invited_agent_must_change_password_before_workspace(): void
    {
        $user = $this->assignedAgent(['must_change_password' => true]);

        $this->post('/agent/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('agent.password.change'));

        $this->assertAuthenticatedAs($user, 'agent');

        $this->get(route('agent.workspace'))
            ->assertRedirect(route('agent.password.change'));

        $this->get(route('agent.password.change'))
            ->assertOk()
            ->assertSee('Choose a new password')
            ->assertDontSee('Current password');
    }

    public function test_first_login_password_change_unlocks_workspace(): void
    {
        $user = $this->assignedAgent(['must_change_password' => true]);

        $this->actingAs($user, 'agent');

        $this->post(route('agent.password.change.update'), [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('agent.workspace'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('new-password-123', $user->password));

        $this->get(route('agent.workspace'))
            ->assertOk()
            ->assertSee('Change password')
            ->assertSee('Get Next Lead');
    }

    public function test_first_login_rejects_the_temporary_password(): void
    {
        $user = $this->assignedAgent(['must_change_password' => true]);

        $this->actingAs($user, 'agent');

        $this->from(route('agent.password.change'))
            ->post(route('agent.password.change.update'), [
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('agent.password.change'))
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_workspace_user_menu_links_to_change_password(): void
    {
        $user = $this->assignedAgent();

        $this->actingAs($user, 'agent')
            ->get(route('agent.workspace'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('Change password')
            ->assertSee('Sign out');
    }

    public function test_logged_in_agent_can_change_password_from_menu_with_current_password(): void
    {
        $user = $this->assignedAgent();

        $this->actingAs($user, 'agent');

        $this->get(route('agent.password.change'))
            ->assertOk()
            ->assertSee('Current password');

        $this->post(route('agent.password.change.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('agent.workspace'));

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_optional_password_change_requires_current_password(): void
    {
        $user = $this->assignedAgent();

        $this->actingAs($user, 'agent')
            ->from(route('agent.password.change'))
            ->post(route('agent.password.change.update'), [
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('agent.password.change'))
            ->assertSessionHasErrors('current_password');
    }

    public function test_password_reset_clears_must_change_password(): void
    {
        $user = $this->assignedAgent(['must_change_password' => true]);
        $token = Password::broker()->createToken($user);

        $this->post(route('agent.password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'reset-password-123',
            'password_confirmation' => 'reset-password-123',
        ])->assertRedirect(route('agent.login'));

        $this->assertFalse($user->fresh()->must_change_password);

        $this->post('/agent/login', [
            'email' => $user->email,
            'password' => 'reset-password-123',
        ])->assertRedirect(route('agent.workspace'));
    }

    public function test_invited_admin_is_sent_to_profile_after_filament_login(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'password' => 'password',
            'must_change_password' => true,
        ]);

        Livewire::test(FilamentLogin::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(url('/admin/profile'));
    }

    public function test_invited_admin_can_set_password_on_filament_profile(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'password' => 'password',
            'must_change_password' => true,
        ]);

        $this->actingAs($user, 'web');

        Livewire::test(EditProfile::class)
            ->fillForm([
                'password' => 'new-password-123',
                'passwordConfirmation' => 'new-password-123',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function assignedAgent(array $overrides = []): User
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'password' => 'password',
            ...$overrides,
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

        return $user;
    }
}
