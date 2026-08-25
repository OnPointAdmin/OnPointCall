<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\ListAssignmentAction;
use App\Enums\UserRole;
use App\Filament\Resources\CallingLists\Pages\EditCallingList;
use App\Filament\Resources\CallingLists\Pages\ListCallingLists;
use App\Filament\Resources\CallingLists\Pages\ViewCallingList;
use App\Filament\Resources\CallingLists\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\CallingLists\RelationManagers\ListAssignmentHistoryRelationManager;
use App\Filament\Resources\CallingLists\Widgets\CallingListDispositionStats;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\LeadTypeDefinition;
use App\Models\ListAssignment;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class ViewCallingListTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_calling_lists_index_opens_view_instead_of_edit(): void
    {
        $admin = $this->makeAdmin();
        $this->createCallingList($admin->company_id, overrides: ['name' => 'Standard']);

        CompanyContext::set($admin->company_id);

        Livewire::actingAs($admin)
            ->test(ListCallingLists::class)
            ->assertOk()
            ->assertTableActionExists('view')
            ->assertTableActionExists('edit');
    }

    public function test_view_page_is_read_only_and_shows_counts_and_assignment_history(): void
    {
        $admin = $this->makeAdmin();
        $agent = User::factory()->create([
            'company_id' => $admin->company_id,
            'role' => UserRole::Agent,
            'active' => true,
            'name' => 'Pat Agent',
        ]);
        $list = $this->createCallingList($admin->company_id, overrides: ['name' => 'Standard']);
        $lead = $this->makeLead($admin->company_id, $list->id, '4045559001');

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $admin->company_id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => ['disposition' => Disposition::Booked->value],
        ]);

        $this->actingAs($admin);
        ListAssignment::withoutGlobalScopes()->create([
            'company_id' => $admin->company_id,
            'user_id' => $agent->id,
            'calling_list_id' => $list->id,
        ]);

        CompanyContext::set($admin->company_id);

        Livewire::actingAs($admin)
            ->test(ViewCallingList::class, ['record' => $list->getRouteKey()])
            ->assertOk()
            ->assertSee('Standard')
            ->assertSee('Edit')
            ->assertDontSee('Save changes')
            ->assertSee('Show leads')
            ->assertDontSee('4045559001')
            ->assertActionExists('edit');

        Livewire::actingAs($admin)
            ->test(CallingListDispositionStats::class, ['record' => $list])
            ->assertOk()
            ->assertSee('Total')
            ->assertSee('Booked')
            ->assertSee('1');

        Livewire::actingAs($admin)
            ->test(ListAssignmentHistoryRelationManager::class, [
                'ownerRecord' => $list,
                'pageClass' => ViewCallingList::class,
            ])
            ->assertOk()
            ->assertSee('Pat Agent')
            ->assertSee(ListAssignmentAction::Assigned->label());
    }

    public function test_show_leads_reveals_leads_on_this_list_only(): void
    {
        $admin = $this->makeAdmin();
        $list = $this->createCallingList($admin->company_id, overrides: ['name' => 'Standard']);
        $otherList = $this->createCallingList($admin->company_id, overrides: ['name' => 'Other']);
        $onList = $this->makeLead($admin->company_id, $list->id, '4045552001');
        $this->makeLead($admin->company_id, $otherList->id, '4045552002');

        CompanyContext::set($admin->company_id);

        Livewire::actingAs($admin)
            ->test(ViewCallingList::class, ['record' => $list->getRouteKey()])
            ->assertOk()
            ->assertDontSee('4045552001')
            ->callAction('toggleLeads')
            ->assertSee('Hide leads');

        Livewire::actingAs($admin)
            ->test(LeadsRelationManager::class, [
                'ownerRecord' => $list,
                'pageClass' => ViewCallingList::class,
            ])
            ->assertOk()
            ->assertCanSeeTableRecords([$onList])
            ->assertDontSee('4045552002');
    }

    public function test_edit_page_can_save_and_return_to_view(): void
    {
        $admin = $this->makeAdmin();
        CompanyContext::set($admin->company_id);
        LeadTypeDefinition::createFromName('Standard', 'standard');
        $list = $this->createCallingList($admin->company_id, overrides: ['name' => 'Standard']);

        Livewire::actingAs($admin)
            ->test(EditCallingList::class, ['record' => $list->getRouteKey()])
            ->assertOk()
            ->assertSee('Save changes')
            ->assertActionExists('view')
            ->fillForm(['name' => 'Renamed List'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed List', $list->fresh()->name);
    }

    private function makeAdmin(): User
    {
        $company = Company::factory()->create();

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);
    }

    private function makeLead(int $companyId, int $listId, string $phone): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => $phone,
            'first_name' => 'Pat',
            'last_name' => 'Callahan',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $listId,
            'imported_at' => now(),
        ]);
    }
}
