<?php

namespace Tests\Feature;

use App\Enums\ListAssignmentAction;
use App\Enums\UserRole;
use App\Mail\UserInviteMail;
use App\Models\Company;
use App\Models\ListAssignment;
use App\Models\ListAssignmentHistory;
use App\Models\User;
use App\Services\Users\UserInviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class ListAssignmentHistoryTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_creating_and_deleting_an_assignment_writes_history(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
            'name' => 'Admin User',
        ]);
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
            'name' => 'Pat Agent',
        ]);
        $list = $this->createCallingList($company->id);

        $this->actingAs($admin);

        $assignment = ListAssignment::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'calling_list_id' => $list->id,
        ]);

        $this->assertDatabaseHas('list_assignment_history', [
            'calling_list_id' => $list->id,
            'user_id' => $agent->id,
            'user_name' => 'Pat Agent',
            'action' => ListAssignmentAction::Assigned->value,
            'actor_id' => $admin->id,
        ]);

        $assignment->delete();

        $this->assertDatabaseHas('list_assignment_history', [
            'calling_list_id' => $list->id,
            'user_id' => $agent->id,
            'user_name' => 'Pat Agent',
            'action' => ListAssignmentAction::Unassigned->value,
            'actor_id' => $admin->id,
        ]);
        $this->assertSame(2, ListAssignmentHistory::withoutGlobalScopes()->count());
    }

    public function test_changing_the_assigned_agent_records_unassigned_then_assigned(): void
    {
        $company = Company::factory()->create();
        $first = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
            'name' => 'First Agent',
        ]);
        $second = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
            'name' => 'Second Agent',
        ]);
        $list = $this->createCallingList($company->id);

        $assignment = ListAssignment::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $first->id,
            'calling_list_id' => $list->id,
        ]);

        $assignment->update(['user_id' => $second->id]);

        $history = ListAssignmentHistory::withoutGlobalScopes()
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $history);
        $this->assertSame(ListAssignmentAction::Assigned, $history[0]->action);
        $this->assertSame('First Agent', $history[0]->user_name);
        $this->assertSame(ListAssignmentAction::Unassigned, $history[1]->action);
        $this->assertSame('First Agent', $history[1]->user_name);
        $this->assertSame(ListAssignmentAction::Assigned, $history[2]->action);
        $this->assertSame('Second Agent', $history[2]->user_name);
    }

    public function test_invite_records_assignment_history(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $list = $this->createCallingList($company->id, overrides: ['name' => 'Standard']);

        $result = app(UserInviteService::class)->invite(
            $company,
            'Jason Paine',
            'jasonpaine1@gmail.com',
            UserRole::Admin,
            [$list->id],
        );

        $this->assertDatabaseHas('list_assignment_history', [
            'calling_list_id' => $list->id,
            'user_id' => $result['user']->id,
            'user_name' => 'Jason Paine',
            'action' => ListAssignmentAction::Assigned->value,
        ]);

        Mail::assertSent(UserInviteMail::class);
    }
}
