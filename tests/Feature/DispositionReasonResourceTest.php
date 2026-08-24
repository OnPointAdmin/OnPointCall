<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\UserRole;
use App\Filament\Resources\DispositionReasons\Pages\CreateDispositionReason;
use App\Filament\Resources\DispositionReasons\Pages\ListDispositionReasons;
use App\Models\Company;
use App\Models\DispositionReason;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DispositionReasonResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_reason_uses_authenticated_company_when_context_is_empty(): void
    {
        $admin = $this->makeAdmin();

        CompanyContext::clear();
        $this->actingAs($admin);

        $reason = DispositionReason::query()->create([
            'disposition' => Disposition::Skip,
            'label' => 'Other',
            'sort_order' => 0,
            'active' => true,
        ]);

        $this->assertSame($admin->company_id, $reason->company_id);
    }

    public function test_admin_can_create_disposition_reason_without_company_context(): void
    {
        $admin = $this->makeAdmin();

        CompanyContext::clear();

        Livewire::actingAs($admin)
            ->test(CreateDispositionReason::class)
            ->fillForm([
                'disposition' => Disposition::Skip->value,
                'label' => 'Other',
                'sort_order' => 0,
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('disposition_reasons', [
            'company_id' => $admin->company_id,
            'disposition' => Disposition::Skip->value,
            'label' => 'Other',
            'active' => true,
        ]);
    }

    public function test_list_can_filter_by_disposition(): void
    {
        $admin = $this->makeAdmin();
        CompanyContext::set($admin->company_id);

        $skip = DispositionReason::withoutGlobalScopes()->create([
            'company_id' => $admin->company_id,
            'disposition' => Disposition::Skip,
            'label' => 'Skip Other',
            'sort_order' => 0,
            'active' => true,
        ]);
        $notQualified = DispositionReason::withoutGlobalScopes()->create([
            'company_id' => $admin->company_id,
            'disposition' => Disposition::NotQualified,
            'label' => 'Age',
            'sort_order' => 1,
            'active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(ListDispositionReasons::class)
            ->assertCanSeeTableRecords([$skip, $notQualified])
            ->filterTable('disposition', Disposition::Skip->value)
            ->assertCanSeeTableRecords([$skip])
            ->assertCanNotSeeTableRecords([$notQualified]);
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
}
