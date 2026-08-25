<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\UserRole;
use App\Filament\Pages\AssignLeads;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\LeadTypeDefinition;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class AssignLeadsTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_assign_leads_page_shows_max_count_and_matching_count(): void
    {
        [$admin, $list] = $this->setUpAssignPage();

        $this->makeHoldingLead($admin->company_id, '4045552001', now()->subDays(2));
        $this->makeHoldingLead($admin->company_id, '4045552002', now()->subDay());

        Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->assertOk()
            ->assertSet('holdingCount', 2)
            ->assertSee('Matching leads')
            ->assertSee('Max Count')
            ->assertFormFieldExists('max_count', 'releaseForm')
            ->assertFormFieldDoesNotExist('release_mode', 'releaseForm')
            ->assertSee($list->name);
    }

    public function test_empty_max_count_assigns_all_matching_leads(): void
    {
        [$admin, $list] = $this->setUpAssignPage();

        $first = $this->makeHoldingLead($admin->company_id, '4045553001', now()->subDays(2));
        $second = $this->makeHoldingLead($admin->company_id, '4045553002', now()->subDay());

        Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->assertSet('holdingCount', 2)
            ->fillForm([
                'calling_list_id' => $list->id,
                'max_count' => null,
            ], 'releaseForm')
            ->call('release')
            ->assertHasNoFormErrors()
            ->assertSet('holdingCount', 0)
            ->assertNotified();

        $first->refresh();
        $second->refresh();

        $this->assertSame(LeadStatus::Callable, $first->status);
        $this->assertSame($list->id, $first->calling_list_id);
        $this->assertSame(LeadStatus::Callable, $second->status);
        $this->assertSame($list->id, $second->calling_list_id);
    }

    public function test_max_count_assigns_that_many_freshest_leads(): void
    {
        [$admin, $list] = $this->setUpAssignPage();

        $oldest = $this->makeHoldingLead($admin->company_id, '4045554001', now()->subDays(3));
        $middle = $this->makeHoldingLead($admin->company_id, '4045554002', now()->subDays(2));
        $newest = $this->makeHoldingLead($admin->company_id, '4045554003', now()->subDay());

        Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->assertSet('holdingCount', 3)
            ->fillForm([
                'calling_list_id' => $list->id,
                'max_count' => 2,
            ], 'releaseForm')
            ->call('release')
            ->assertHasNoFormErrors()
            ->assertSet('holdingCount', 1)
            ->assertNotified();

        $oldest->refresh();
        $middle->refresh();
        $newest->refresh();

        $this->assertSame(LeadStatus::Holding, $oldest->status);
        $this->assertNull($oldest->calling_list_id);

        $this->assertSame(LeadStatus::Callable, $middle->status);
        $this->assertSame($list->id, $middle->calling_list_id);

        $this->assertSame(LeadStatus::Callable, $newest->status);
        $this->assertSame($list->id, $newest->calling_list_id);
    }

    public function test_attempt_count_filter_limits_matching_leads(): void
    {
        [$admin] = $this->setUpAssignPage();

        $this->makeHoldingLead($admin->company_id, '4045555001', now()->subDay(), attemptCount: 0);
        $this->makeHoldingLead($admin->company_id, '4045555002', now()->subDay(), attemptCount: 2);

        Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->assertSet('holdingCount', 2)
            ->fillForm([
                'attempt_count' => 2,
            ], 'filterForm')
            ->call('refreshCountAction')
            ->assertSet('holdingCount', 1);
    }

    public function test_list_source_shows_matching_callable_leads(): void
    {
        [$admin, $targetList] = $this->setUpAssignPage();

        $sourceList = $this->createCallingList($admin->company_id, overrides: [
            'name' => 'Source List',
        ]);

        $this->makeCallableLead($admin->company_id, $sourceList->id, '4045556001');
        $this->makeCallableLead($admin->company_id, $sourceList->id, '4045556002');
        $this->makeHoldingLead($admin->company_id, '4045556003', now());

        Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->fillForm([
                'source_calling_list_id' => (string) $sourceList->id,
            ], 'filterForm')
            ->call('refreshCountAction')
            ->assertSet('holdingCount', 2)
            ->fillForm([
                'calling_list_id' => $targetList->id,
                'max_count' => null,
            ], 'releaseForm')
            ->call('release')
            ->assertHasNoFormErrors()
            ->assertSet('holdingCount', 0)
            ->assertNotified();

        $this->assertSame(0, Lead::withoutGlobalScopes()
            ->where('company_id', $admin->company_id)
            ->where('calling_list_id', $sourceList->id)
            ->count());

        $this->assertSame(2, Lead::withoutGlobalScopes()
            ->where('company_id', $admin->company_id)
            ->where('calling_list_id', $targetList->id)
            ->count());
    }

    public function test_list_to_list_assignment_preserves_attempt_count(): void
    {
        [$admin, $targetList] = $this->setUpAssignPage();

        $sourceList = $this->createCallingList($admin->company_id, overrides: [
            'name' => 'Source List',
        ]);

        $lead = $this->makeCallableLead($admin->company_id, $sourceList->id, '4045557001', attemptCount: 3);

        Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->fillForm([
                'source_calling_list_id' => (string) $sourceList->id,
            ], 'filterForm')
            ->fillForm([
                'calling_list_id' => $targetList->id,
                'max_count' => null,
            ], 'releaseForm')
            ->call('release')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $lead->refresh();

        $this->assertSame($targetList->id, $lead->calling_list_id);
        $this->assertSame(LeadStatus::Callable, $lead->status);
        $this->assertSame(3, $lead->attempt_count);

        $this->assertDatabaseHas('lead_history', [
            'lead_id' => $lead->id,
            'event_type' => LeadHistoryType::Assign->value,
        ]);
    }

    /**
     * @return array{0: User, 1: CallingList}
     */
    private function setUpAssignPage(): array
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        CompanyContext::set($company->id);

        LeadTypeDefinition::createFromName('Standard', 'standard');

        $list = $this->createCallingList($company->id, overrides: [
            'name' => 'Standard List',
        ]);

        return [$admin, $list];
    }

    private function makeHoldingLead(int $companyId, string $phone, mixed $importedAt, int $attemptCount = 0): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => $phone,
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::Qualified,
            'imported_at' => $importedAt,
            'attempt_count' => $attemptCount,
        ]);
    }

    private function makeCallableLead(
        int $companyId,
        int $callingListId,
        string $phone,
        int $attemptCount = 0,
    ): Lead {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => $phone,
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::Qualified,
            'calling_list_id' => $callingListId,
            'imported_at' => now(),
            'attempt_count' => $attemptCount,
        ]);
    }
}
