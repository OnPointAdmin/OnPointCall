<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\PasswordResetMail;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\ListAssignment;
use App\Models\User;
use App\Support\PasswordResetContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        PasswordResetContext::reset();
    }

    public function test_agent_forgot_password_sends_reset_mail_with_agent_url(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
        ]);

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && str_contains($mail->resetUrl, '/agent/reset-password/');
        });
    }

    public function test_unknown_email_still_returns_success_without_mail(): void
    {
        Mail::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'missing@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Mail::assertNothingSent();
    }

    public function test_inactive_user_gets_success_without_mail(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => false,
        ]);

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Mail::assertNothingSent();
    }

    public function test_agent_can_reset_password_with_valid_token_and_login(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'password' => bcrypt('old-password'),
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

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('agent.password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'new-password-123',
        ]);

        $loginResponse->assertRedirect(route('agent.workspace'));
    }

    public function test_agent_reset_password_fails_with_invalid_token(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        $response = $this->post(route('agent.password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    public function test_forgot_password_page_is_available_at_shared_url(): void
    {
        $this->get('/forgot-password')
            ->assertOk();

        $this->get('/admin/password-reset/request')
            ->assertRedirect('/forgot-password');
    }
}
