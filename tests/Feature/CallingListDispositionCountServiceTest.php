<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Services\CallingLists\CallingListDispositionCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class CallingListDispositionCountServiceTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_counts_last_disposition_of_leads_currently_on_the_list(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);
        $list = $this->createCallingList($company->id, overrides: ['name' => 'Standard']);
        $otherList = $this->createCallingList($company->id, overrides: ['name' => 'Other']);

        $bookedA = $this->makeLead($company->id, $list->id, '4045551001');
        $bookedB = $this->makeLead($company->id, $list->id, '4045551002');
        $noAnswer = $this->makeLead($company->id, $list->id, '4045551003');
        $this->makeLead($company->id, $list->id, '4045551004');
        $otherLead = $this->makeLead($company->id, $otherList->id, '4045551005');

        $this->addDisposition($company->id, $bookedA->id, $admin->id, Disposition::NoAnswer);
        $this->addDisposition($company->id, $bookedA->id, $admin->id, Disposition::Booked);
        $this->addDisposition($company->id, $bookedB->id, $admin->id, Disposition::Booked);
        $this->addDisposition($company->id, $noAnswer->id, $admin->id, Disposition::NoAnswer);
        $this->addDisposition($company->id, $otherLead->id, $admin->id, Disposition::LeftVm);

        $items = collect(app(CallingListDispositionCountService::class)->forList($list))
            ->mapWithKeys(fn (array $item): array => [$item['key'] => $item['count']]);

        $this->assertSame(4, $items['total']);
        $this->assertSame(2, $items[Disposition::Booked->value]);
        $this->assertSame(1, $items[Disposition::NoAnswer->value]);
        $this->assertSame(1, $items['none']);
        $this->assertArrayNotHasKey(Disposition::LeftVm->value, $items->all());
    }

    private function makeLead(int $companyId, int $listId, string $phone): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => $phone,
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $listId,
            'imported_at' => now(),
        ]);
    }

    private function addDisposition(int $companyId, int $leadId, int $actorId, Disposition $disposition): void
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
