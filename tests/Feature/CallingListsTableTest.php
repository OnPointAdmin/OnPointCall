<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Resources\CallingLists\Pages\ListCallingLists;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Models\StateRule;
use App\Models\User;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class CallingListsTableTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_calling_lists_page_shows_total_and_dialable_inventory_counts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));

        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        $this->seedStateRules($company->id);

        $cadence = $this->createCadenceWithDayParts($company->id, ['morning', 'afternoon', 'evening']);
        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'lead_type' => 'standard',
            'cadence_id' => $cadence->id,
            'active' => true,
        ]);

        $this->makeLead($company->id, $list->id, LeadStatus::Callable, '4045551001');
        $this->makeLead($company->id, $list->id, LeadStatus::Callable, '4045551002');
        $this->makeLead(
            $company->id,
            $list->id,
            LeadStatus::Callable,
            '4045551003',
            attemptCount: 1,
            lastAttemptAt: now()->subMinutes(5),
        );
        $this->makeLead($company->id, $list->id, LeadStatus::Booked, '4045551004');
        $this->makeLead($company->id, $list->id, LeadStatus::Terminal, '4045551005');

        $list->loadCount('leads');

        $this->assertSame(5, $list->leads_count);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListCallingLists::class)
            ->assertOk()
            ->assertSee('Total leads')
            ->assertSee('Ready now')
            ->assertSee('Waiting')
            ->assertSee('5')
            ->assertSee('2')
            ->assertSee('1');
    }

    private function seedStateRules(int $companyId): void
    {
        StateRule::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'state_code' => 'NY',
            'window_start' => '08:00:00',
            'window_end' => '21:00:00',
            'permitted_weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'manual_dial_only' => false,
        ]);

        StateRule::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'state_code' => 'DEFAULT',
            'window_start' => '08:00:00',
            'window_end' => '21:00:00',
            'permitted_weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'manual_dial_only' => false,
        ]);
    }

    private function makeLead(
        int $companyId,
        int $listId,
        LeadStatus $status,
        string $phone,
        int $attemptCount = 0,
        ?Carbon $lastAttemptAt = null,
    ): Lead {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => $phone,
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => $status,
            'lead_type' => 'standard',
            'calling_list_id' => $listId,
            'attempt_count' => $attemptCount,
            'last_attempt_at' => $lastAttemptAt,
            'imported_at' => now(),
        ]);
    }
}
