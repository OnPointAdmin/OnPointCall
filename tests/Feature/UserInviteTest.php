<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\UserInviteMail;
use App\Models\AllowedEmail;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\ListAssignment;
use App\Models\User;
use App\Services\Users\UserInviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_creates_user_allowlist_assignment_and_sends_mail(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'lead_type' => 'standard',
            'active' => true,
        ]);

        $result = app(UserInviteService::class)->invite(
            $company,
            'Jason Paine',
            'jasonpaine1@gmail.com',
            UserRole::Admin,
            [$list->id],
        );

        $user = $result['user'];

        $this->assertSame('jasonpaine1@gmail.com', $user->email);
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->active);
        $this->assertNotEmpty($result['password']);

        $this->assertTrue(
            AllowedEmail::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('email', 'jasonpaine1@gmail.com')
                ->exists()
        );

        $this->assertTrue(
            ListAssignment::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('calling_list_id', $list->id)
                ->exists()
        );

        Mail::assertSent(UserInviteMail::class, function (UserInviteMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->user->is($user)
                && $mail->envelope()->subject === 'You are invited to OnPoint Call';
        });
    }

    public function test_artisan_invite_command_sends_mail(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'lead_type' => 'standard',
            'active' => true,
        ]);

        $this->artisan('user:invite', [
            'email' => 'jasonpaine1@gmail.com',
            '--name' => 'Jason Paine',
            '--role' => 'admin',
            '--lists' => 'Standard',
            '--company' => $company->id,
        ])->assertSuccessful();

        $this->assertTrue(
            User::withoutGlobalScopes()
                ->where('email', 'jasonpaine1@gmail.com')
                ->where('company_id', $company->id)
                ->exists()
        );

        Mail::assertSent(UserInviteMail::class);
    }

    public function test_resend_invite_resets_password_and_sends_mail(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'jasonpaine1@gmail.com',
            'role' => UserRole::Agent,
        ]);

        $password = app(UserInviteService::class)->resend($user);

        $this->assertNotEmpty($password);

        Mail::assertSent(UserInviteMail::class, function (UserInviteMail $mail) use ($user, $password): bool {
            return $mail->hasTo($user->email) && $mail->plainPassword === $password;
        });
    }

    public function test_role_coerce_accepts_enum_or_string(): void
    {
        $this->assertSame(UserRole::Admin, UserRole::coerce(UserRole::Admin));
        $this->assertSame(UserRole::Agent, UserRole::coerce('agent'));
    }
}
