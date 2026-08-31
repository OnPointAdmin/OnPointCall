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

        $first = $this->makeHoldingLead($admin->company_id, '4045552001', now()->subDays(2));
        $second = $this->makeHoldingLead($admin->company_id, '4045552002', now()->subDay());

        Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->assertOk()
            ->assertSet('holdingCount', 2)
            ->assertSee('Matching leads')
            ->assertSee('Selected leads')
            ->assertSee('Max Count')
            ->assertFormFieldExists('max_count', 'releaseForm')
            ->assertFormFieldDoesNotExist('release_mode', 'releaseForm')
            ->assertSee($list->name)
            ->assertCanSeeTableRecords([$first, $second]);
    }

    public function test_selected_leads_table_view_opens_slide_over_with_full_record_and_history(): void
    {
        [$admin] = $this->setUpAssignPage();

        $lead = $this->makeHoldingLead($admin->company_id, '4045552100', now()->subDay());
        $lead->update([
            'first_name' => 'Pat',
            'last_name' => 'Callahan',
            'email' => 'pat@example.com',
            'address' => '1291 E Strawberry Drive',
            'city' => 'Gilbert',
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $admin->company_id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => [
                'disposition' => Disposition::LeftVm->value,
                'note' => 'Left a voicemail about the tour.',
            ],
        ]);

        $component = Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->assertOk()
            ->assertTableActionExists('view')
            ->mountTableAction('view', $lead)
            ->assertTableActionDataSet([
                'phone' => '4045552100',
                'first_name' => 'Pat',
                'last_name' => 'Callahan',
                'email' => 'pat@example.com',
                'address' => '1291 E Strawberry Drive',
                'city' => 'Gilbert',
            ]);

        $this->assertNull($component->instance()->getMountedAction()?->getUrl());

        $schemaHtml = $component->instance()
            ->getSchema($component->instance()->getMountedActionSchemaName())
            ?->toHtml() ?? '';

        $this->assertStringContainsString('History', $schemaHtml);
        $this->assertStringContainsString('Left VM', $schemaHtml);
        $this->assertStringContainsString($admin->name, $schemaHtml);
        $this->assertStringContainsString('Left a voicemail about the tour.', $schemaHtml);
    }

    public function test_selected_leads_table_limits_to_max_count_freshest(): void
    {
        [$admin] = $this->setUpAssignPage();

        $oldest = $this->makeHoldingLead($admin->company_id, '4045552101', now()->subDays(3));
        $middle = $this->makeHoldingLead($admin->company_id, '4045552102', now()->subDays(2));
        $newest = $this->makeHoldingLead($admin->company_id, '4045552103', now()->subDay());

        Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->assertSet('holdingCount', 3)
            ->assertCanSeeTableRecords([$oldest, $middle, $newest])
            ->fillForm([
                'max_count' => 2,
            ], 'releaseForm')
            ->assertSee('The 2 freshest of 3 matching leads.')
            ->assertCanSeeTableRecords([$middle, $newest])
            ->assertCanNotSeeTableRecords([$oldest]);
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

    public function test_null_qualification_holding_leads_are_included_by_default(): void
    {
        [$admin] = $this->setUpAssignPage();

        Lead::withoutGlobalScopes()->create([
            'company_id' => $admin->company_id,
            'phone' => '4045558001',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => null,
            'imported_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->assertSet('holdingCount', 1)
            ->fillForm([
                'qualification_status' => QualificationStatus::Qualified->value,
            ], 'filterForm')
            ->call('refreshCountAction')
            ->assertSet('holdingCount', 0);
    }

    public function test_create_date_filters_limit_matching_leads(): void
    {
        [$admin] = $this->setUpAssignPage();

        $inside = $this->makeHoldingLead($admin->company_id, '4045559001', now(), originalSubmitDate: '2026-06-15');
        $before = $this->makeHoldingLead($admin->company_id, '4045559002', now(), originalSubmitDate: '2026-05-01');
        $after = $this->makeHoldingLead($admin->company_id, '4045559003', now(), originalSubmitDate: '2026-07-01');

        Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->assertSet('holdingCount', 3)
            ->assertFormFieldExists('created_from', 'filterForm')
            ->assertFormFieldExists('created_to', 'filterForm')
            ->fillForm([
                'created_from' => '2026-06-01',
                'created_to' => '2026-06-30',
            ], 'filterForm')
            ->call('refreshCountAction')
            ->assertSet('holdingCount', 1)
            ->assertCanSeeTableRecords([$inside])
            ->assertCanNotSeeTableRecords([$before, $after]);
    }

    public function test_attempt_count_filter_limits_matching_leads(): void
    {
        [$admin] = $this->setUpAssignPage();

        $zeroAttempts = $this->makeHoldingLead($admin->company_id, '4045555001', now()->subDay(), attemptCount: 0);
        $twoAttempts = $this->makeHoldingLead($admin->company_id, '4045555002', now()->subDay(), attemptCount: 2);

        Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->assertSet('holdingCount', 2)
            ->assertCanSeeTableRecords([$zeroAttempts, $twoAttempts])
            ->fillForm([
                'attempt_count' => 2,
            ], 'filterForm')
            ->call('refreshCountAction')
            ->assertSet('holdingCount', 1)
            ->assertCanSeeTableRecords([$twoAttempts])
            ->assertCanNotSeeTableRecords([$zeroAttempts]);
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

    private function makeHoldingLead(
        int $companyId,
        string $phone,
        mixed $importedAt,
        int $attemptCount = 0,
        ?string $originalSubmitDate = null,
    ): Lead {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => $phone,
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::Qualified,
            'imported_at' => $importedAt,
            'attempt_count' => $attemptCount,
            'original_lead_submit_date' => $originalSubmitDate,
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
