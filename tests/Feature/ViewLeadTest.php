<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Resources\Leads\RelationManagers\HistoryRelationManager;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_list_uses_view_action_instead_of_edit(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListLeads::class)
            ->assertOk()
            ->assertTableActionExists('view')
            ->assertTableActionDoesNotExist('edit');
    }

    public function test_lead_view_page_is_read_only_and_shows_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/New_York'));

        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
            'name' => 'Admin User',
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559001',
            'first_name' => 'Pat',
            'last_name' => 'Callahan',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => Carbon::parse('2026-08-10 14:00:00', 'America/New_York'),
            'payload' => [
                'disposition' => Disposition::LeftVm->value,
                'note' => 'Left a voicemail about the tour.',
            ],
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ViewLead::class, ['record' => $lead->getRouteKey()])
            ->assertOk()
            ->assertSee('4045559001')
            ->assertSee('Pat')
            ->assertSee('Edit')
            ->assertDontSee('Save changes')
            ->assertSee('History');

        Livewire::actingAs($admin)
            ->test(HistoryRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => ViewLead::class,
            ])
            ->assertOk()
            ->assertSee('Left VM')
            ->assertSee('Admin User')
            ->assertSee('Left a voicemail about the tour.');
    }

    public function test_lead_history_detail_label_formats_disposition_and_status_change(): void
    {
        $history = new LeadHistory([
            'event_type' => LeadHistoryType::Disposition,
            'payload' => [
                'disposition' => Disposition::Callback->value,
                'callback_at' => '2026-08-11T10:00:00-04:00',
            ],
        ]);

        $this->assertSame('Callback · Callback: Aug 11, 10:00 AM EDT', $history->detailLabel());

        $statusChange = new LeadHistory([
            'event_type' => LeadHistoryType::StatusChange,
            'payload' => [
                'from' => LeadStatus::Callable->value,
                'to' => LeadStatus::Terminal->value,
                'reason' => 'rnd_reassigned',
            ],
        ]);

        $this->assertSame('Callable → Terminal (rnd_reassigned)', $statusChange->detailLabel());

        $fieldEdit = new LeadHistory([
            'event_type' => LeadHistoryType::FieldEdit,
            'payload' => [
                'changes' => [
                    'email' => ['from' => 'a@example.com', 'to' => 'b@example.com'],
                    'zip' => ['from' => '30000', 'to' => '30301'],
                    'age_range' => ['from' => '45-54', 'to' => '55-64'],
                ],
            ],
        ]);

        $this->assertSame(
            'Email: a@example.com → b@example.com; Zip: 30000 → 30301; Age range: 45-54 → 55-64',
            $fieldEdit->detailLabel(),
        );
    }
}
