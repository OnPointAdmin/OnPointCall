<?php

namespace Tests\Feature;

use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Services\Leads\CallingListAssignedAtBackfill;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class CallingListAssignedAtTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-10 15:00:00', 'America/New_York'));
    }

    public function test_assigning_a_lead_to_a_list_stamps_added_to_list(): void
    {
        $company = Company::factory()->create();
        $list = $this->createCallingList($company->id);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551001',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $this->assertNull($lead->calling_list_assigned_at);

        $lead->update(['calling_list_id' => $list->id]);

        $this->assertTrue($lead->calling_list_assigned_at->equalTo(now()));
    }

    public function test_moving_a_lead_to_another_list_updates_added_to_list(): void
    {
        $company = Company::factory()->create();
        $source = $this->createCallingList($company->id, overrides: ['name' => 'Source']);
        $target = $this->createCallingList($company->id, overrides: ['name' => 'Target']);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551001',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $source->id,
            'imported_at' => now(),
        ]);

        $this->assertTrue($lead->calling_list_assigned_at->equalTo(now()));

        Carbon::setTestNow(now()->addDay());

        $lead->update(['calling_list_id' => $target->id]);

        $this->assertTrue($lead->calling_list_assigned_at->equalTo(now()));
    }

    public function test_removing_a_lead_from_a_list_clears_added_to_list(): void
    {
        $company = Company::factory()->create();
        $list = $this->createCallingList($company->id);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551001',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'imported_at' => now(),
        ]);

        $lead->update(['calling_list_id' => null]);

        $this->assertNull($lead->calling_list_assigned_at);
    }

    public function test_explicit_added_to_list_is_preserved_when_setting_list(): void
    {
        $company = Company::factory()->create();
        $list = $this->createCallingList($company->id);
        $assignedAt = now()->subDays(5);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551001',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'calling_list_assigned_at' => $assignedAt,
            'imported_at' => now(),
        ]);

        $this->assertTrue($lead->calling_list_assigned_at->equalTo($assignedAt));
    }

    public function test_backfill_uses_latest_matching_release_or_assign_history(): void
    {
        $company = Company::factory()->create();
        $firstList = $this->createCallingList($company->id, overrides: ['name' => 'First']);
        $currentList = $this->createCallingList($company->id, overrides: ['name' => 'Current']);

        $released = $this->leadOnListWithoutTimestamp($company->id, $currentList->id, '4045551001');
        $moved = $this->leadOnListWithoutTimestamp($company->id, $currentList->id, '4045551002');
        $noHistory = $this->leadOnListWithoutTimestamp($company->id, $currentList->id, '4045551003');
        $alreadyStamped = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551004',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $currentList->id,
            'calling_list_assigned_at' => now()->subDays(9),
            'imported_at' => now(),
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $released->id,
            'event_type' => LeadHistoryType::Release,
            'occurred_at' => now()->subDays(4),
            'payload' => [
                'calling_list_id' => $currentList->id,
                'calling_list_name' => 'Current',
            ],
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $moved->id,
            'event_type' => LeadHistoryType::Release,
            'occurred_at' => now()->subDays(8),
            'payload' => [
                'calling_list_id' => $firstList->id,
                'calling_list_name' => 'First',
            ],
        ]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $moved->id,
            'event_type' => LeadHistoryType::Assign,
            'occurred_at' => now()->subDays(2),
            'payload' => [
                'from_calling_list_id' => $firstList->id,
                'to_calling_list_id' => $currentList->id,
                'to_calling_list_name' => 'Current',
            ],
        ]);

        $updated = app(CallingListAssignedAtBackfill::class)->run();

        $this->assertSame(2, $updated);
        $this->assertTrue($released->fresh()->calling_list_assigned_at->equalTo(now()->subDays(4)));
        $this->assertTrue($moved->fresh()->calling_list_assigned_at->equalTo(now()->subDays(2)));
        $this->assertNull($noHistory->fresh()->calling_list_assigned_at);
        $this->assertTrue($alreadyStamped->fresh()->calling_list_assigned_at->equalTo(now()->subDays(9)));
    }

    private function leadOnListWithoutTimestamp(int $companyId, int $listId, string $phone): Lead
    {
        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => $phone,
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        DB::table('leads')->where('id', $lead->id)->update([
            'calling_list_id' => $listId,
            'status' => LeadStatus::Callable->value,
            'calling_list_assigned_at' => null,
        ]);

        return $lead->fresh();
    }
}
