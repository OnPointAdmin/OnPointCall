<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class LeadsTableTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_lead_list_shows_last_disposition_and_last_call_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/New_York')->utc());

        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/Los_Angeles',
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045559001',
            'external_lead_id' => 'CRM-9001',
            'first_name' => 'Pat',
            'last_name' => 'Callahan',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'last_attempt_at' => Carbon::parse('2026-08-10 14:00:00', 'America/New_York')->utc(),
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => Carbon::parse('2026-08-09 11:00:00', 'America/New_York'),
            'payload' => ['disposition' => Disposition::NoAnswer->value],
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'actor_id' => $admin->id,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => Carbon::parse('2026-08-10 14:00:00', 'America/New_York'),
            'payload' => ['disposition' => Disposition::LeftVm->value],
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListLeads::class)
            ->assertOk()
            ->assertSee('External ID')
            ->assertSee('CRM-9001')
            ->assertSee('Last Disp')
            ->assertSee('Last Call Date')
            ->assertSee('Left VM')
            ->assertSee('Aug 10, 2026')
            ->assertSee('11:00')
            ->assertDontSee('18:00')
            ->filterTable('last_disposition', Disposition::NoAnswer->value)
            ->assertCanNotSeeTableRecords([$lead]);
    }

    public function test_lead_list_can_filter_by_last_disposition(): void
    {
        [$company, $admin] = $this->makeAdminCompany();

        $leftVm = $this->makeLead($company->id, phone: '4045559001');
        $noAnswer = $this->makeLead($company->id, phone: '4045559002');
        $neverCalled = $this->makeLead($company->id, phone: '4045559003');

        $this->makeDisposition($company->id, $leftVm->id, $admin->id, Disposition::LeftVm);
        $this->makeDisposition($company->id, $noAnswer->id, $admin->id, Disposition::NoAnswer);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListLeads::class)
            ->assertCanSeeTableRecords([$leftVm, $noAnswer, $neverCalled])
            ->filterTable('last_disposition', Disposition::LeftVm->value)
            ->assertCanSeeTableRecords([$leftVm])
            ->assertCanNotSeeTableRecords([$noAnswer, $neverCalled])
            ->filterTable('last_disposition', 'none')
            ->assertCanSeeTableRecords([$neverCalled])
            ->assertCanNotSeeTableRecords([$leftVm, $noAnswer]);
    }

    public function test_lead_list_can_filter_calling_list_including_holding(): void
    {
        [$company, $admin] = $this->makeAdminCompany();
        $list = $this->createCallingList($company->id, overrides: ['name' => 'Standard']);

        $holding = $this->makeLead($company->id, phone: '4045559001');
        $onList = $this->makeLead($company->id, callingListId: $list->id, phone: '4045559002');

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListLeads::class)
            ->assertCanSeeTableRecords([$holding, $onList])
            ->filterTable('calling_list_id', 'holding')
            ->assertCanSeeTableRecords([$holding])
            ->assertCanNotSeeTableRecords([$onList])
            ->filterTable('calling_list_id', $list->id)
            ->assertCanSeeTableRecords([$onList])
            ->assertCanNotSeeTableRecords([$holding]);
    }

    public function test_lead_list_can_filter_by_venue_and_event(): void
    {
        [$company, $admin] = $this->makeAdminCompany();

        $floridaPrime = $this->makeLead($company->id, phone: '4045559001', venue: 'Florida Event 1', event: 'Prime Expo');
        $floridaSpring = $this->makeLead($company->id, phone: '4045559002', venue: 'Florida Event 1', event: 'Spring Show');
        $georgiaPrime = $this->makeLead($company->id, phone: '4045559003', venue: 'Georgia Event 2', event: 'Prime Expo');
        $unlabeled = $this->makeLead($company->id, phone: '4045559004');

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ListLeads::class)
            ->assertCanSeeTableRecords([$floridaPrime, $floridaSpring, $georgiaPrime, $unlabeled])
            ->filterTable('venue', 'Florida Event 1')
            ->assertCanSeeTableRecords([$floridaPrime, $floridaSpring])
            ->assertCanNotSeeTableRecords([$georgiaPrime, $unlabeled])
            ->filterTable('event', 'Prime Expo')
            ->assertCanSeeTableRecords([$floridaPrime])
            ->assertCanNotSeeTableRecords([$floridaSpring, $georgiaPrime, $unlabeled]);
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

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/Los_Angeles',
        ]);

        return [$company, $admin];
    }

    private function makeLead(
        int $companyId,
        ?int $callingListId = null,
        string $phone = '4045559001',
        ?string $venue = null,
        ?string $event = null,
    ): Lead {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'calling_list_id' => $callingListId,
            'phone' => $phone,
            'first_name' => 'Pat',
            'last_name' => 'Callahan',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'venue' => $venue,
            'event' => $event,
        ]);
    }

    private function makeDisposition(int $companyId, int $leadId, int $actorId, Disposition $disposition): void
    {
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => now(),
            'payload' => ['disposition' => $disposition->value],
        ]);
    }
}
