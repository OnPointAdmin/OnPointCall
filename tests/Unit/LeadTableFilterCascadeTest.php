<?php

namespace Tests\Unit;

use App\Enums\LeadStatus;
use App\Enums\LeadTablePreset;
use App\Enums\QualificationStatus;
use App\Enums\QualifiedPartnersMatch;
use App\Enums\UserRole;
use App\Filament\Pages\AssignLeads;
use App\Filament\Support\LeadTableFilterCascade;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadTypeDefinition;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadTableFilterCascadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_clears_state_when_lead_type_narrows_pool(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        CompanyContext::set($company->id);

        LeadTypeDefinition::createFromName('Standard', 'standard');
        LeadTypeDefinition::createFromName('TNB', 'tnb');

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045558301',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'state' => 'FL',
            'qualification_status' => QualificationStatus::Qualified,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045558302',
            'status' => LeadStatus::Holding,
            'lead_type' => 'tnb',
            'state' => 'NV',
            'qualification_status' => QualificationStatus::Qualified,
            'imported_at' => now(),
        ]);

        $component = Livewire::actingAs($admin)->test(AssignLeads::class);

        $tableFilters = [
            'lead_type' => ['value' => 'tnb'],
            'calling_list_id' => ['value' => 'holding'],
            'qualified_partners_match' => ['value' => QualifiedPartnersMatch::InList->value],
            'state' => ['values' => ['FL']],
        ];

        $table = $component->instance()->getTable();

        $changed = LeadTableFilterCascade::prune($tableFilters, $table, LeadTablePreset::Assign, 'lead_type');

        $this->assertTrue($changed);
        $this->assertSame([], $tableFilters['state']['values']);
        $this->assertSame('tnb', $tableFilters['lead_type']['value']);
    }
}
