<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\DispositionOutcome;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Dispositions\Pages\CreateDisposition;
use App\Filament\Resources\Dispositions\Pages\EditDisposition;
use App\Models\Company;
use App\Models\DispositionDefinition;
use App\Models\DispositionReason;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Services\Leads\DispositionService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DispositionDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_creation_seeds_standard_dispositions(): void
    {
        $company = Company::factory()->create();

        $this->assertDatabaseHas('dispositions', [
            'company_id' => $company->id,
            'slug' => Disposition::Booked->value,
            'is_system' => true,
            'active' => true,
        ]);

        $this->assertSame(
            10,
            DispositionDefinition::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('is_system', true)
                ->count(),
        );
    }

    public function test_custom_terminal_disposition_applies_and_sets_lead_terminal(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        $definition = DispositionDefinition::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'slug' => 'language-barrier',
            'label' => 'Language Barrier',
            'sort_order' => 99,
            'active' => true,
            'is_system' => false,
            'outcome' => DispositionOutcome::Terminal,
            'increments_attempt' => true,
            'requires_reason' => false,
            'button_group' => 'negative',
            'color' => 'red',
            'report_group' => 'other',
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045558801',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $updated = app(DispositionService::class)->apply(
            $lead,
            $user,
            $definition->slug,
        );

        $this->assertSame(LeadStatus::Terminal, $updated->status);
        $this->assertDatabaseHas('lead_history', [
            'lead_id' => $lead->id,
            'event_type' => LeadHistoryType::Disposition->value,
        ]);
    }

    public function test_deactivated_standard_disposition_still_applies_from_admin_action(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Manager,
        ]);

        DispositionDefinition::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('slug', Disposition::Dnc->value)
            ->update(['active' => false]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045558802',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $updated = app(DispositionService::class)->apply(
            $lead,
            $user,
            Disposition::Dnc->value,
        );

        $this->assertSame(LeadStatus::Dnc, $updated->status);
    }

    public function test_admin_can_create_custom_disposition(): void
    {
        $admin = $this->makeAdmin();
        CompanyContext::clear();

        Livewire::actingAs($admin)
            ->test(CreateDisposition::class)
            ->fillForm([
                'label' => 'Language Barrier',
                'slug' => 'language-barrier',
                'outcome' => DispositionOutcome::Terminal->value,
                'increments_attempt' => true,
                'requires_reason' => false,
                'button_group' => 'negative',
                'color' => 'red',
                'report_group' => 'other',
                'sort_order' => 50,
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('dispositions', [
            'company_id' => $admin->company_id,
            'slug' => 'language-barrier',
            'is_system' => false,
        ]);
    }

    public function test_admin_can_deactivate_booked_disposition(): void
    {
        $admin = $this->makeAdmin();
        $booked = DispositionDefinition::withoutGlobalScopes()
            ->where('company_id', $admin->company_id)
            ->where('slug', Disposition::Booked->value)
            ->firstOrFail();

        CompanyContext::set($admin->company_id);

        Livewire::actingAs($admin)
            ->test(EditDisposition::class, ['record' => $booked->id])
            ->fillForm(['active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('dispositions', [
            'id' => $booked->id,
            'active' => false,
        ]);
    }

    public function test_standard_disposition_cannot_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $booked = DispositionDefinition::withoutGlobalScopes()
            ->where('company_id', $admin->company_id)
            ->where('slug', Disposition::Booked->value)
            ->firstOrFail();

        CompanyContext::set($admin->company_id);

        Livewire::actingAs($admin)
            ->test(EditDisposition::class, ['record' => $booked->id])
            ->assertActionHidden('delete');

        $this->assertDatabaseHas('dispositions', ['id' => $booked->id]);
    }

    public function test_custom_disposition_with_reason_applies_terminal(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        DispositionDefinition::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'slug' => 'custom-ni',
            'label' => 'Custom NI',
            'sort_order' => 99,
            'active' => true,
            'is_system' => false,
            'outcome' => DispositionOutcome::Terminal,
            'increments_attempt' => true,
            'requires_reason' => true,
            'button_group' => 'negative',
            'color' => 'red',
            'report_group' => 'other',
        ]);

        $reason = DispositionReason::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'disposition' => 'custom-ni',
            'label' => 'Spanish only',
            'sort_order' => 1,
            'active' => true,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045558803',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        app(DispositionService::class)->apply(
            $lead,
            $user,
            'custom-ni',
            reason: (string) $reason->id,
        );

        $this->assertSame(LeadStatus::Terminal, $lead->fresh()->status);
    }

    private function makeAdmin(): User
    {
        $company = Company::factory()->create();

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
        ]);
    }
}
