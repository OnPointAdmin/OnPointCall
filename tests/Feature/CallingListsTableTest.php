<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Resources\CallingLists\Pages\ListCallingLists;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CallingListsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_calling_lists_page_shows_total_and_available_lead_counts(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'lead_type' => 'standard',
            'cadence' => ['day_parts' => ['morning'], 'min_gap_minutes' => 60],
            'active' => true,
        ]);

        $this->makeLead($company->id, $list->id, LeadStatus::Callable, '4045551001');
        $this->makeLead($company->id, $list->id, LeadStatus::Callable, '4045551002');
        $this->makeLead($company->id, $list->id, LeadStatus::Callable, '4045551003');
        $this->makeLead($company->id, $list->id, LeadStatus::Booked, '4045551004');
        $this->makeLead($company->id, $list->id, LeadStatus::Terminal, '4045551005');

        $list->loadCount(['leads', 'availableLeads']);

        $this->assertSame(5, $list->leads_count);
        $this->assertSame(3, $list->available_leads_count);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListCallingLists::class)
            ->assertOk()
            ->assertSee('Total leads')
            ->assertSee('Available leads')
            ->assertSee('5')
            ->assertSee('3');
    }

    private function makeLead(int $companyId, int $listId, LeadStatus $status, string $phone): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => $phone,
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => $status,
            'lead_type' => 'standard',
            'calling_list_id' => $listId,
            'imported_at' => now(),
        ]);
    }
}
