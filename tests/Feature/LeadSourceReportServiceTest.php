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
use App\Services\Dashboard\LeadSourceReportService;
use App\Services\Dashboard\ManagerDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class LeadSourceReportServiceTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_report_groups_bookings_by_venue_and_event(): void
    {
        $company = Company::factory()->create();
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        $venueALead = $this->createLead($company->id, 'standard', venue: 'Venue A', event: 'Event 1');
        $venueBLead = $this->createLead($company->id, 'standard', venue: 'Venue B', event: 'Event 2');
        $blankLead = $this->createLead($company->id, 'standard');

        $this->createDisposition($company->id, $venueALead->id, $agent->id, Disposition::Booked);
        $this->createDisposition($company->id, $venueALead->id, $agent->id, Disposition::NotInterested);
        $this->createDisposition($company->id, $venueBLead->id, $agent->id, Disposition::Booked);
        $this->createDisposition($company->id, $venueBLead->id, $agent->id, Disposition::Booked);
        $this->createDisposition($company->id, $blankLead->id, $agent->id, Disposition::NoAnswer);
        $this->createSkip($company->id, $blankLead->id, $agent->id);

        $service = app(LeadSourceReportService::class);
        $range = app(ManagerDashboardService::class)->todayRange($company->id);
        $report = $service->report($company->id, null, null, $range['start'], $range['end']);

        $this->assertSame(6, $report['totals']['total_leads_called']);
        $this->assertSame(3, $report['totals']['booked']);
        $this->assertSame(50.0, $report['totals']['booked_percent']);

        $this->assertCount(3, $report['rows']);
        $this->assertSame('Venue B', $report['rows'][0]['venue']);
        $this->assertSame('Event 2', $report['rows'][0]['event']);
        $this->assertSame(2, $report['rows'][0]['booked']);
        $this->assertSame(100.0, $report['rows'][0]['booked_percent']);

        $this->assertSame('Venue A', $report['rows'][1]['venue']);
        $this->assertSame(1, $report['rows'][1]['booked']);
        $this->assertSame(50.0, $report['rows'][1]['booked_percent']);

        $this->assertSame('(none)', $report['rows'][2]['venue']);
        $this->assertSame('(none)', $report['rows'][2]['event']);
        $this->assertSame(0, $report['rows'][2]['booked']);
        $this->assertSame(0.0, $report['rows'][2]['booked_percent']);
    }

    public function test_report_filters_by_agent(): void
    {
        $company = Company::factory()->create();
        $agentOne = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);
        $agentTwo = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        $lead = $this->createLead($company->id, 'standard', venue: 'Venue A', event: 'Event 1');

        $this->createDisposition($company->id, $lead->id, $agentOne->id, Disposition::Booked);
        $this->createDisposition($company->id, $lead->id, $agentTwo->id, Disposition::Booked);

        $service = app(LeadSourceReportService::class);
        $range = app(ManagerDashboardService::class)->todayRange($company->id);
        $report = $service->report($company->id, $agentOne->id, null, $range['start'], $range['end']);

        $this->assertSame(1, $report['totals']['total_leads_called']);
        $this->assertSame(1, $report['totals']['booked']);
    }

    public function test_report_isolates_companies(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $agentA = User::factory()->create([
            'company_id' => $companyA->id,
            'role' => UserRole::Agent,
        ]);
        $agentB = User::factory()->create([
            'company_id' => $companyB->id,
            'role' => UserRole::Agent,
        ]);

        $leadA = $this->createLead($companyA->id, 'standard', venue: 'Venue A', event: 'Event 1');
        $leadB = $this->createLead($companyB->id, 'standard', venue: 'Venue B', event: 'Event 2');

        $this->createDisposition($companyA->id, $leadA->id, $agentA->id, Disposition::Booked);
        $this->createDisposition($companyB->id, $leadB->id, $agentB->id, Disposition::Booked);

        $service = app(LeadSourceReportService::class);
        $range = app(ManagerDashboardService::class)->todayRange($companyA->id);
        $report = $service->report($companyA->id, null, null, $range['start'], $range['end']);

        $this->assertSame(1, $report['totals']['booked']);
        $this->assertSame('Venue A', $report['rows'][0]['venue']);
    }

    private function createLead(
        int $companyId,
        string $leadType,
        ?string $venue = null,
        ?string $event = null,
        ?int $callingListId = null,
    ): Lead {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'phone' => '404555'.random_int(1000, 9999),
            'status' => LeadStatus::Callable,
            'lead_type' => $leadType,
            'venue' => $venue,
            'event' => $event,
            'calling_list_id' => $callingListId,
            'imported_at' => now(),
        ]);
    }

    private function createDisposition(
        int $companyId,
        int $leadId,
        int $actorId,
        Disposition $disposition,
        ?Carbon $occurredAt = null,
    ): void {
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'event_type' => LeadHistoryType::Disposition,
            'occurred_at' => $occurredAt ?? now(),
            'payload' => ['disposition' => $disposition->value],
        ]);
    }

    private function createSkip(int $companyId, int $leadId, int $actorId): void
    {
        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'event_type' => LeadHistoryType::Skip,
            'occurred_at' => now(),
            'payload' => [],
        ]);
    }
}
