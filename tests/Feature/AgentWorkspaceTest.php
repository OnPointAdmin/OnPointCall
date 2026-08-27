<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\EmptyQueueReason;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\SoftScoreStatus;
use App\Enums\UserRole;
use App\Jobs\QualifyLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Livewire\Agent\Workspace;
use App\Models\AppSetting;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadClaim;
use App\Models\LeadHistory;
use App\Models\ListAssignment;
use App\Models\StateRule;
use App\Models\User;
use App\Services\Leads\NextLeadService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AgentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        Queue::fake();
    }

    public function test_save_lead_edits_updates_allowed_fields_without_clearing_lead(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
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

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045555001',
            'first_name' => 'Pat',
            'last_name' => 'Lee',
            'email' => 'old@example.com',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip' => '30301',
            'address' => '1 Main St',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'imported_at' => now(),
        ]);

        LeadClaim::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'claimed_at' => now(),
            'expires_at' => now()->addMinutes(20),
        ]);

        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->call('startEdit')
            ->set('editable.email', 'new@example.com')
            ->set('editable.city', 'Savannah')
            ->set('editable.state', 'GA')
            ->set('editable.zip', '31401')
            ->set('editable.address', '2 River Rd')
            ->set('editable.address_2', 'Suite 3')
            ->set('editable.age_range', '30 - 39')
            ->set('editable.annual_income', '$75k - $99k')
            ->set('editable.marital_status', 'Married')
            ->set('editable.gender', 'Female')
            ->set('editable.home_owner', 'Homeowner (3+ years)')
            ->call('saveLeadEdits')
            ->assertSet('leadId', $lead->id)
            ->assertSet('editable', []);

        $lead->refresh();

        $this->assertSame('new@example.com', $lead->email);
        $this->assertSame('Savannah', $lead->city);
        $this->assertSame('31401', $lead->zip);
        $this->assertSame('2 River Rd', $lead->address);
        $this->assertSame('Suite 3', $lead->address_2);
        $this->assertSame('30 - 39', $lead->age_range);
        $this->assertSame('$75k - $99k', $lead->annual_income);
        $this->assertSame('Married', $lead->marital_status);
        $this->assertSame('Female', $lead->gender);
        $this->assertSame('Homeowner (3+ years)', $lead->home_owner);

        $edits = LeadHistory::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('event_type', LeadHistoryType::FieldEdit)
            ->get();

        $this->assertCount(1, $edits);
        $this->assertSame($user->id, $edits->first()->actor_id);
        $this->assertArrayHasKey('email', $edits->first()->payload['changes']);
        $this->assertArrayHasKey('zip', $edits->first()->payload['changes']);
        $this->assertArrayHasKey('age_range', $edits->first()->payload['changes']);
        $this->assertStringContainsString('Email:', $edits->first()->detailLabel());
        $this->assertStringContainsString('Zip:', $edits->first()->detailLabel());
        $this->assertStringContainsString('Age range:', $edits->first()->detailLabel());
    }

    public function test_edit_dropdowns_include_imported_demographic_values_not_in_canonical_lists(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
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

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045555002',
            'first_name' => 'Aaron',
            'last_name' => 'Davis',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'age_range' => '30 - 59',
            'annual_income' => '$80,000 - $90,000',
            'marital_status' => 'Widowed',
            'gender' => 'Non-binary',
            'home_owner' => 'Renter',
            'imported_at' => now(),
        ]);

        LeadClaim::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'claimed_at' => now(),
            'expires_at' => now()->addMinutes(20),
        ]);

        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->call('startEdit')
            ->assertSeeHtml('value="30 - 59"')
            ->assertSeeHtml('value="$80,000 - $90,000"')
            ->assertSeeHtml('value="Widowed"')
            ->assertSeeHtml('value="Non-binary"')
            ->assertSeeHtml('value="Renter"');
    }

    public function test_save_lead_edits_writes_one_field_edit_history_row(): void
    {
        [$user, $lead] = $this->makeWorkableLead();
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->call('startEdit')
            ->set('editable.email', 'new@example.com')
            ->set('editable.zip', '31401')
            ->call('saveLeadEdits');

        $history = LeadHistory::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('event_type', LeadHistoryType::FieldEdit)
            ->get();

        $this->assertCount(1, $history);
        $label = $history->first()->detailLabel();
        $this->assertStringContainsString('Email:', $label);
        $this->assertStringContainsString('Zip:', $label);
        $this->assertStringContainsString(';', $label);
    }

    public function test_agent_call_history_only_includes_own_rows(): void
    {
        [$user, $lead] = $this->makeWorkableLead();
        $other = User::factory()->create([
            'company_id' => $user->company_id,
            'name' => 'Other Agent History',
            'role' => UserRole::Agent,
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'actor_id' => $other->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now()->subHour(),
            'payload' => [
                'disposition' => Disposition::NoAnswer->value,
                'note' => 'other-agent-only-note',
            ],
        ]);
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'actor_id' => $user->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => [
                'disposition' => Disposition::LeftVm->value,
                'note' => 'own-agent-only-note',
            ],
        ]);

        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->assertSee('Left VM')
            ->assertSee('own-agent-only-note')
            ->assertSee('1 events')
            ->assertDontSee('other-agent-only-note');
    }

    public function test_manager_call_history_includes_all_rows(): void
    {
        [$agent, $lead] = $this->makeWorkableLead();
        $manager = User::factory()->create([
            'company_id' => $agent->company_id,
            'role' => UserRole::Manager,
        ]);
        ListAssignment::withoutGlobalScopes()->create([
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'calling_list_id' => $lead->calling_list_id,
        ]);
        LeadClaim::withoutGlobalScopes()->where('lead_id', $lead->id)->update(['user_id' => $manager->id]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'actor_id' => $agent->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now()->subHour(),
            'payload' => [
                'disposition' => Disposition::NoAnswer->value,
                'note' => 'agent-history-visible-to-manager',
            ],
        ]);
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'actor_id' => $manager->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => [
                'disposition' => Disposition::LeftVm->value,
                'note' => 'manager-own-history-note',
            ],
        ]);

        $this->actingAs($manager, 'agent');

        Livewire::test(Workspace::class)
            ->assertSee('Left VM')
            ->assertSee('No Answer')
            ->assertSee('agent-history-visible-to-manager')
            ->assertSee('manager-own-history-note')
            ->assertSee('2 events');
    }

    public function test_load_lead_queues_blank_soft_score_and_qualification(): void
    {
        [$user, $lead] = $this->makeWorkableLead();
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class);

        Queue::assertPushed(SoftScoreLeadJob::class, function (SoftScoreLeadJob $job) use ($lead): bool {
            return $job->leadId === $lead->id && $job->dispatchQualificationAfter === true;
        });
    }

    public function test_open_callback_loads_owned_callback(): void
    {
        [$user, $lead] = $this->makeWorkableLead([
            'status' => LeadStatus::Callback,
            'callback_owner_id' => null,
            'callback_at' => now()->subHour(),
            'timezone' => 'America/New_York',
            'state' => 'GA',
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);
        $lead->update([
            'callback_owner_id' => $user->id,
        ]);

        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->call('openCallback', $lead->id)
            ->assertSet('leadId', $lead->id)
            ->assertSee('Put Back');
    }

    public function test_put_back_keeps_callback_and_clears_workspace(): void
    {
        [$user, $lead] = $this->makeWorkableLead([
            'status' => LeadStatus::Callback,
            'callback_at' => now()->subHour(),
            'timezone' => 'America/New_York',
            'state' => 'GA',
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);
        $lead->update([
            'callback_owner_id' => $user->id,
        ]);

        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->call('putBackCallback')
            ->assertSet('leadId', null)
            ->assertSee('Callback kept on your list');

        $lead->refresh();

        $this->assertSame(LeadStatus::Callback, $lead->status);
        $this->assertSame($user->id, $lead->callback_owner_id);
        $this->assertNotNull($lead->callback_at);
        $this->assertSame(0, $lead->attempt_count);
        $this->assertDatabaseMissing('lead_claims', ['lead_id' => $lead->id]);
    }

    public function test_put_back_does_not_apply_to_pool_leads(): void
    {
        [$user, $lead] = $this->makeWorkableLead([
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->assertSee('Skip')
            ->assertDontSee('Put Back')
            ->call('putBackCallback')
            ->assertSet('leadId', $lead->id);

        $this->assertDatabaseHas('lead_claims', ['lead_id' => $lead->id]);
    }

    public function test_put_back_restores_previously_claimed_pool_lead(): void
    {
        [$user, $poolLead] = $this->makeWorkableLead([
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);

        $callback = Lead::withoutGlobalScopes()->create([
            'company_id' => $user->company_id,
            'phone' => '4045555101',
            'first_name' => 'Kim',
            'status' => LeadStatus::Callback,
            'lead_type' => 'standard',
            'calling_list_id' => $poolLead->calling_list_id,
            'callback_owner_id' => $user->id,
            'callback_at' => now()->subHour(),
            'timezone' => 'America/New_York',
            'state' => 'GA',
            'imported_at' => now(),
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);

        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->assertSet('leadId', $poolLead->id)
            ->call('openCallback', $callback->id)
            ->assertSet('leadId', $callback->id)
            ->call('putBackCallback')
            ->assertSet('leadId', $poolLead->id);

        $callback->refresh();

        $this->assertSame(LeadStatus::Callback, $callback->status);
        $this->assertSame($user->id, $callback->callback_owner_id);
        $this->assertDatabaseMissing('lead_claims', ['lead_id' => $callback->id]);
        $this->assertDatabaseHas('lead_claims', ['lead_id' => $poolLead->id]);
    }

    public function test_not_interested_requires_reason(): void
    {
        [$user, $lead] = $this->makeWorkableLead([
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->call('applyDisposition', 'not_interested')
            ->assertHasErrors(['dispositionReasonId'])
            ->assertSet('leadId', $lead->id);
    }

    public function test_skip_requires_reason(): void
    {
        [$user, $lead] = $this->makeWorkableLead([
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->call('applyDisposition', 'skip')
            ->assertHasErrors(['dispositionReasonId'])
            ->assertSet('leadId', $lead->id);
    }

    public function test_callback_time_uses_app_setting_timezone_and_is_not_due_until_that_local_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/New_York')->utc());

        [$user, $lead] = $this->makeWorkableLead([
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $user->company_id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/Los_Angeles',
        ]);

        StateRule::withoutGlobalScopes()->create([
            'company_id' => $user->company_id,
            'state_code' => 'NY',
            'window_start' => '08:00:00',
            'window_end' => '21:00:00',
            'permitted_weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'manual_dial_only' => false,
        ]);

        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->set('callbackAt', '2026-08-10T16:00')
            ->call('applyDisposition', 'callback')
            ->assertHasNoErrors()
            ->assertSee('4:00 PM PDT');

        $lead->refresh();

        $expected = Carbon::parse('2026-08-10 16:00:00', 'America/Los_Angeles');
        $this->assertTrue($lead->callback_at->equalTo($expected));
        $this->assertFalse($lead->callback_at->equalTo(Carbon::parse('2026-08-10 16:00:00', 'UTC')));

        $result = app(NextLeadService::class)->getNext($user);
        $this->assertFalse($result->hasLead());
        $this->assertSame(EmptyQueueReason::NoneAvailable, $result->emptyReason);

        Carbon::setTestNow(Carbon::parse('2026-08-10 16:00:00', 'America/Los_Angeles')->utc());

        $result = app(NextLeadService::class)->getNext($user);
        $this->assertTrue($result->hasLead());
        $this->assertSame($lead->id, $result->lead?->id);

        Carbon::setTestNow();
    }

    public function test_callback_requires_date_time(): void
    {
        [$user, $lead] = $this->makeWorkableLead([
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->assertSeeHtml('type="date"')
            ->assertSeeHtml('type="time"')
            ->call('applyDisposition', 'callback')
            ->assertHasErrors(['callbackAt'])
            ->assertSet('leadId', $lead->id);
    }

    public function test_workspace_displays_last_call_and_history_in_app_setting_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/New_York')->utc());

        [$user, $lead] = $this->makeWorkableLead([
            'last_attempt_at' => Carbon::parse('2026-08-10 14:00:00', 'America/New_York')->utc(),
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => Carbon::parse('2026-08-10 14:00:00', 'America/New_York')->utc(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $user->company_id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/Los_Angeles',
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $user->company_id,
            'lead_id' => $lead->id,
            'actor_id' => $user->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => Carbon::parse('2026-08-10 14:00:00', 'America/New_York')->utc(),
            'payload' => ['disposition' => Disposition::LeftVm->value],
        ]);

        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->assertSee('11:00 AM PDT')
            ->assertSee('Last checked Aug 10, 2026')
            ->assertDontSee('6:00 PM');

        Carbon::setTestNow();
    }

    public function test_phone_number_button_wires_copy_handler(): void
    {
        [$user, $lead] = $this->makeWorkableLead([
            'phone' => '4045555100',
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->assertSee('Click to copy phone number')
            ->assertSeeHtml('id="phone-copy-btn"')
            ->assertSeeHtml('data-phone="'.$lead->phone.'"')
            ->assertSeeHtml('window.opcCopyPhone')
            ->assertDontSeeHtml('onclick="copyPhone');

        $this->get(route('agent.workspace'))
            ->assertOk()
            ->assertSee('window.opcCopyPhone', false)
            ->assertSee('id="phone-copy-btn"', false)
            ->assertSee('data-phone="'.$lead->phone.'"', false);
    }

    public function test_lead_panel_shows_tnb_fields_and_hides_partners(): void
    {
        [$user] = $this->makeWorkableLead([
            'lead_type' => 'tnb',
            'tour_location' => 'Orlando',
            'tour_date_start' => '2026-08-01',
            'booking_id' => 'BK-100',
            'extra_fields' => ['gift' => 'Show tickets'],
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->assertSee('Qualified to Tour At')
            ->assertSee('Source Information')
            ->assertSee('Lead type')
            ->assertSee('Tour / TNB')
            ->assertSee('Tour location')
            ->assertSee('Orlando')
            ->assertSee('Tour date start')
            ->assertSee('Booking ID')
            ->assertSee('BK-100')
            ->assertSee('Gift')
            ->assertSee('Show tickets')
            ->assertDontSee('Tour result')
            ->assertDontSee('Extra fields')
            ->assertDontSeeHtml('>Partners</p>');
    }

    public function test_lead_panel_hides_empty_tour_section_on_tnb(): void
    {
        [$user] = $this->makeWorkableLead([
            'lead_type' => 'tnb',
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->assertSee('Lead type')
            ->assertDontSee('Tour / TNB')
            ->assertDontSee('Tour location');
    }

    public function test_lead_panel_hides_empty_tour_section_on_standard_leads(): void
    {
        [$user] = $this->makeWorkableLead([
            'original_lead_submit_date' => '2026-05-13',
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->assertSee('Original submit date')
            ->assertSee('2026-05-13')
            ->assertDontSee('Tour / TNB')
            ->assertDontSee('Tour location');
    }

    public function test_refresh_does_not_requeue_blank_soft_score(): void
    {
        [$user] = $this->makeWorkableLead();
        $this->actingAs($user, 'agent');

        $component = Livewire::test(Workspace::class);

        Queue::assertPushed(SoftScoreLeadJob::class, 1);

        $component->call('$refresh');

        Queue::assertPushed(SoftScoreLeadJob::class, 1);
    }

    public function test_saving_name_forces_soft_score_and_qualification(): void
    {
        [$user] = $this->makeWorkableLead([
            'first_name' => 'Pat',
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now()->subDay(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now()->subDay(),
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->call('startEdit')
            ->set('editable.first_name', 'Patricia')
            ->call('saveLeadEdits')
            ->assertSeeHtml('data-score-check="soft-score"')
            ->assertSeeHtml('data-score-check="qualification"')
            ->assertSeeHtml('wire:poll.2s');

        Queue::assertPushed(SoftScoreLeadJob::class, 1);
        Queue::assertPushed(
            SoftScoreLeadJob::class,
            fn (SoftScoreLeadJob $job): bool => $job->force === true && $job->dispatchQualificationAfter === true,
        );
    }

    public function test_saving_demographics_forces_qualification_only(): void
    {
        [$user] = $this->makeWorkableLead([
            'age_range' => '45-54',
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now()->subDay(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now()->subDay(),
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->call('startEdit')
            ->set('editable.age_range', '55-64')
            ->call('saveLeadEdits')
            ->assertDontSeeHtml('data-score-check="soft-score"')
            ->assertSeeHtml('data-score-check="qualification"')
            ->assertSeeHtml('wire:poll.2s');

        Queue::assertNotPushed(SoftScoreLeadJob::class);
        Queue::assertPushed(
            QualifyLeadJob::class,
            fn (QualifyLeadJob $job): bool => $job->force === true,
        );
    }

    public function test_saving_email_does_not_show_score_spinners(): void
    {
        [$user] = $this->makeWorkableLead([
            'email' => 'old@example.com',
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now()->subDay(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now()->subDay(),
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->call('startEdit')
            ->set('editable.email', 'new@example.com')
            ->call('saveLeadEdits')
            ->assertDontSeeHtml('data-score-check="soft-score"')
            ->assertDontSeeHtml('data-score-check="qualification"')
            ->assertSeeHtml('wire:poll.10s');

        Queue::assertNotPushed(SoftScoreLeadJob::class);
        Queue::assertNotPushed(QualifyLeadJob::class);
    }

    public function test_score_spinners_clear_after_checks_complete(): void
    {
        [$user, $lead] = $this->makeWorkableLead([
            'first_name' => 'Pat',
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now()->subDay(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now()->subDay(),
        ]);
        $this->actingAs($user, 'agent');

        $component = Livewire::test(Workspace::class)
            ->call('startEdit')
            ->set('editable.first_name', 'Patricia')
            ->call('saveLeadEdits')
            ->assertSeeHtml('data-score-check="soft-score"')
            ->assertSeeHtml('data-score-check="qualification"');

        $lead->refresh();
        $lead->update([
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'B2',
            'soft_score_checked_at' => now()->addSecond(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now()->addSecond(),
        ]);

        $component->call('$refresh')
            ->assertDontSeeHtml('data-score-check="soft-score"')
            ->assertDontSeeHtml('data-score-check="qualification"')
            ->assertSee('B2')
            ->assertSeeHtml('wire:poll.10s');
    }

    public function test_run_buttons_hidden_when_checked_within_fifteen_days(): void
    {
        [$user] = $this->makeWorkableLead([
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'B',
            'soft_score_checked_at' => now()->subDays(1),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now()->subDays(1),
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->assertDontSee('Run Soft Score')
            ->assertDontSee('Run Qualification');
    }

    public function test_lookup_opens_callable_lead_without_calling_list(): void
    {
        [$user, $lead] = $this->makeLookupLead([
            'phone' => '3057208602',
            'first_name' => 'Francisco',
            'last_name' => 'Bedoya',
            'state' => 'FL',
            'status' => LeadStatus::Callable,
            'calling_list_id' => null,
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->set('lookupQuery', '3057208602')
            ->call('searchLeads')
            ->assertSee('Francisco')
            ->call('selectLookupLead', $lead->id)
            ->assertSet('leadId', $lead->id)
            ->assertSet('leadReadOnly', false)
            ->assertSee('Francisco Bedoya');

        $this->assertDatabaseHas('lead_claims', [
            'lead_id' => $lead->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_lookup_opens_holding_lead(): void
    {
        [$user, $lead] = $this->makeLookupLead([
            'status' => LeadStatus::Holding,
            'calling_list_id' => null,
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->call('selectLookupLead', $lead->id)
            ->assertSet('leadId', $lead->id)
            ->assertSet('leadReadOnly', false);
    }

    public function test_lookup_opens_dnc_lead_read_only_with_message(): void
    {
        [$user, $lead] = $this->makeLookupLead([
            'status' => LeadStatus::Dnc,
        ]);
        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->call('selectLookupLead', $lead->id)
            ->assertSet('leadId', $lead->id)
            ->assertSet('leadReadOnly', true)
            ->assertSee('DNC — this lead cannot be worked');

        $this->assertDatabaseMissing('lead_claims', ['lead_id' => $lead->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Lead}
     */
    private function makeWorkableLead(array $overrides = []): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
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

        $lead = Lead::withoutGlobalScopes()->create(array_merge([
            'company_id' => $company->id,
            'phone' => '4045555100',
            'first_name' => 'Pat',
            'last_name' => 'Lee',
            'email' => 'old@example.com',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip' => '30301',
            'address' => '1 Main St',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'imported_at' => now(),
        ], $overrides));

        LeadClaim::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'claimed_at' => now(),
            'expires_at' => now()->addMinutes(20),
        ]);

        return [$user, $lead];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Lead}
     */
    private function makeLookupLead(array $overrides = []): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        $lead = Lead::withoutGlobalScopes()->create(array_merge([
            'company_id' => $company->id,
            'phone' => '4045556100',
            'first_name' => 'Pat',
            'last_name' => 'Lee',
            'city' => 'Atlanta',
            'state' => 'GA',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A',
            'soft_score_checked_at' => now(),
            'qualification_status' => QualificationStatus::Qualified,
            'qualification_checked_at' => now(),
        ], $overrides));

        return [$user, $lead];
    }

    public function test_scoreboard_shows_logged_in_agent_totals_and_date_presets(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 15:00:00', 'America/New_York'));

        [$user, $lead] = $this->makeWorkableLead();
        $otherAgent = User::factory()->create([
            'company_id' => $user->company_id,
            'role' => UserRole::Agent,
        ]);

        $this->createWorkspaceDisposition($user->company_id, $lead->id, $user->id, Disposition::Booked, now());
        $this->createWorkspaceDisposition($user->company_id, $lead->id, $user->id, Disposition::NoAnswer, now());
        $this->createWorkspaceSkip($user->company_id, $lead->id, $user->id, now());
        $this->createWorkspaceDisposition($user->company_id, $lead->id, $otherAgent->id, Disposition::Booked, now());
        $this->createWorkspaceDisposition($user->company_id, $lead->id, $user->id, Disposition::NotInterested, now()->subDay());

        Lead::withoutGlobalScopes()->create([
            'company_id' => $user->company_id,
            'phone' => '4045559100',
            'status' => LeadStatus::Callback,
            'lead_type' => 'standard',
            'callback_owner_id' => $user->id,
            'callback_at' => now()->subHour(),
            'imported_at' => now(),
        ]);

        $this->actingAs($user, 'agent');

        $component = Livewire::test(Workspace::class)
            ->assertSet('scoreboardPreset', 'today')
            ->assertSee('Total Leads Called')
            ->assertSee('Booked')
            ->assertSee('Not Interested')
            ->assertSee('Not Qualified')
            ->assertSee('No Answer / VM')
            ->assertSee('Wrong / DNC')
            ->assertSee('Skipped')
            ->assertSee('Call Backs')
            ->assertSee('Overdue Call Backs')
            ->assertSee('Yesterday')
            ->assertSee('This Week')
            ->assertSee('Last Week')
            ->assertSee('MTD')
            ->assertSee('YTD');

        $today = $component->instance()->scoreboard;
        $this->assertSame(3, $today['total_leads_called']['count']);
        $this->assertSame(1, $today['booked']['count']);
        $this->assertSame(1, $today['no_answer_vm']['count']);
        $this->assertSame(1, $today['skipped']['count']);
        $this->assertSame(0, $today['not_interested']['count']);
        $this->assertSame(1, $today['overdue_callbacks']['count']);

        $component->call('setScoreboardPreset', 'yesterday');
        $yesterday = $component->instance()->scoreboard;
        $this->assertSame('yesterday', $component->get('scoreboardPreset'));
        $this->assertSame(1, $yesterday['total_leads_called']['count']);
        $this->assertSame(1, $yesterday['not_interested']['count']);
        $this->assertSame(0, $yesterday['booked']['count']);
        $this->assertSame(1, $yesterday['overdue_callbacks']['count']);

        $component->call('setScoreboardPreset', 'mtd');
        $mtd = $component->instance()->scoreboard;
        $this->assertSame(4, $mtd['total_leads_called']['count']);
        $this->assertSame(1, $mtd['booked']['count']);
        $this->assertSame(1, $mtd['not_interested']['count']);

        $component->call('setScoreboardPreset', 'not_a_preset');
        $this->assertSame('mtd', $component->get('scoreboardPreset'));

        Carbon::setTestNow();
    }

    private function createWorkspaceDisposition(
        int $companyId,
        int $leadId,
        int $actorId,
        Disposition $disposition,
        Carbon $occurredAt,
    ): void {
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => $occurredAt,
            'payload' => ['disposition' => $disposition->value],
        ]);
    }

    private function createWorkspaceSkip(int $companyId, int $leadId, int $actorId, Carbon $occurredAt): void
    {
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'event_type' => LeadHistoryType::Skip,
            'occurred_at' => $occurredAt,
            'payload' => [],
        ]);
    }
}
