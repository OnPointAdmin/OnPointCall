<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Services\Dashboard\DashboardDigestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class DashboardDigestServiceTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_digest_html_includes_results_by_rep_and_nested_lists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 15:00:00', 'America/New_York'));

        $company = Company::factory()->create(['name' => 'OnPoint']);
        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/New_York',
        ]);

        $alice = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'name' => 'Alice Rep',
        ]);
        $bob = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'name' => 'Bob Rep',
        ]);

        $standardList = $this->createCallingList($company->id, overrides: ['name' => 'Standard AM']);
        $tnbList = $this->createCallingList($company->id, overrides: ['name' => 'TNB']);
        $soloList = $this->createCallingList($company->id, overrides: ['name' => 'Solo List']);

        $aliceStandard = $this->createLead($company->id, $standardList->id);
        $aliceTnb = $this->createLead($company->id, $tnbList->id);
        $bobSolo = $this->createLead($company->id, $soloList->id);

        $occurredAt = Carbon::parse('2026-08-25 14:00:00', 'America/New_York');
        $this->createDisposition($company->id, $aliceStandard->id, $alice->id, Disposition::Booked, $occurredAt);
        $this->createDisposition($company->id, $aliceTnb->id, $alice->id, Disposition::NotInterested, $occurredAt);
        $this->createDisposition($company->id, $bobSolo->id, $bob->id, Disposition::Booked, $occurredAt);

        $digest = app(DashboardDigestService::class)->buildForCompany(
            $company,
            Carbon::parse('2026-08-25', 'America/New_York'),
        );

        $this->assertStringContainsString('Results by Rep', $digest['html']);
        $this->assertStringContainsString('Booked', $digest['html']);
        $this->assertStringContainsString('No Answer / VM', $digest['html']);
        $this->assertStringContainsString('Alice Rep', $digest['html']);
        $this->assertStringContainsString('Standard AM', $digest['html']);
        $this->assertStringContainsString('TNB', $digest['html']);
        $this->assertStringContainsString('Bob Rep', $digest['html']);
        $this->assertStringNotContainsString('Solo List', $digest['html']);
        $this->assertSame(3, $digest['stats']['total_leads_called']);
        $this->assertSame(2, $digest['stats']['booked']);

        Carbon::setTestNow();
    }

    private function createLead(int $companyId, int $callingListId): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => '404555'.random_int(1000, 9999),
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $callingListId,
            'imported_at' => now(),
        ]);
    }

    private function createDisposition(
        int $companyId,
        int $leadId,
        int $actorId,
        Disposition $disposition,
        Carbon $occurredAt,
    ): void {
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => $occurredAt,
            'payload' => ['disposition' => $disposition->value],
        ]);
    }
}
