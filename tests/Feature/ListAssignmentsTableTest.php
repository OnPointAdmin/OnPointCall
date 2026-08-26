<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ListAssignments\Pages\ListListAssignments;
use App\Models\Company;
use App\Models\ListAssignment;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class ListAssignmentsTableTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_list_can_filter_by_user_and_calling_list(): void
    {
        [$company, $admin] = $this->makeAdminCompany();
        $alice = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
            'name' => 'Alice Agent',
        ]);
        $bob = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
            'name' => 'Bob Agent',
        ]);
        $standard = $this->createCallingList($company->id, overrides: ['name' => 'Standard']);
        $priority = $this->createCallingList($company->id, overrides: ['name' => 'Priority']);

        $aliceStandard = $this->makeAssignment($company->id, $alice->id, $standard->id);
        $alicePriority = $this->makeAssignment($company->id, $alice->id, $priority->id);
        $bobStandard = $this->makeAssignment($company->id, $bob->id, $standard->id);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListListAssignments::class)
            ->assertCanSeeTableRecords([$aliceStandard, $alicePriority, $bobStandard])
            ->filterTable('user_id', $alice->id)
            ->assertCanSeeTableRecords([$aliceStandard, $alicePriority])
            ->assertCanNotSeeTableRecords([$bobStandard])
            ->filterTable('calling_list_id', $standard->id)
            ->assertCanSeeTableRecords([$aliceStandard])
            ->assertCanNotSeeTableRecords([$alicePriority, $bobStandard]);
    }

    public function test_list_can_sort_by_user_and_calling_list(): void
    {
        [$company, $admin] = $this->makeAdminCompany();
        $zoe = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
            'name' => 'Zoe Agent',
        ]);
        $amy = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
            'name' => 'Amy Agent',
        ]);
        $zebra = $this->createCallingList($company->id, overrides: ['name' => 'Zebra']);
        $alpha = $this->createCallingList($company->id, overrides: ['name' => 'Alpha']);

        $zoeZebra = $this->makeAssignment($company->id, $zoe->id, $zebra->id);
        $amyAlpha = $this->makeAssignment($company->id, $amy->id, $alpha->id);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListListAssignments::class)
            ->sortTable('user.name')
            ->assertCanSeeTableRecords([$amyAlpha, $zoeZebra], inOrder: true)
            ->sortTable('user.name', 'desc')
            ->assertCanSeeTableRecords([$zoeZebra, $amyAlpha], inOrder: true)
            ->sortTable('callingList.name')
            ->assertCanSeeTableRecords([$amyAlpha, $zoeZebra], inOrder: true)
            ->sortTable('callingList.name', 'desc')
            ->assertCanSeeTableRecords([$zoeZebra, $amyAlpha], inOrder: true);
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function makeAdminCompany(): array
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        return [$company, $admin];
    }

    private function makeAssignment(int $companyId, int $userId, int $callingListId): ListAssignment
    {
        return ListAssignment::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'calling_list_id' => $callingListId,
        ]);
    }
}
