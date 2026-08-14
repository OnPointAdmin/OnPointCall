<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Livewire\Agent\Workspace;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadClaim;
use App\Models\ListAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
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
}
