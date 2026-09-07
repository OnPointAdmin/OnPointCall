<?php

namespace App\Services\Dashboard;

use App\Enums\LeadSourceGroupBy;
use App\Enums\LeadStatus;
use App\Enums\ReportSchedulePeriod;
use App\Enums\ReportScheduleType;
use App\Models\Company;
use App\Models\ReportSchedule;
use Carbon\Carbon;

class ReportScheduleMailService
{
    public function __construct(
        private readonly ManagerDashboardService $dashboard,
        private readonly DashboardDigestService $agentDigest,
        private readonly LeadDashboardService $leadDashboard,
        private readonly LeadSourceReportService $leadSource,
    ) {}

    /**
     * @return array{subject: string, html: string}
     */
    public function build(ReportSchedule $schedule, ?Carbon $now = null): array
    {
        $company = $schedule->company()->withoutGlobalScopes()->first()
            ?? Company::withoutGlobalScopes()->findOrFail($schedule->company_id);

        return match ($schedule->report_type) {
            ReportScheduleType::LeadDashboard => $this->buildLeadDashboard($company),
            ReportScheduleType::LeadSource => $this->buildLeadSource($company, $schedule, $now),
            default => $this->agentDigest->buildForPeriod(
                $company,
                $schedule->period ?? ReportSchedulePeriod::Yesterday,
                $now,
            ),
        };
    }

    /**
     * @return array{subject: string, html: string}
     */
    private function buildLeadDashboard(Company $company): array
    {
        $snapshot = $this->leadDashboard->snapshot($company->id);
        $statusOrder = ['holding', 'callable', 'callback', 'booked', 'terminal', 'dnc'];
        $statusLabels = [];

        foreach ($statusOrder as $status) {
            $statusLabels[$status] = LeadStatus::tryFrom($status)?->label() ?? $status;
        }

        $subject = sprintf(
            '%s — Lead Dashboard %s (As of now)',
            config('app.name'),
            $snapshot->runAt,
        );

        $html = view('mail.lead-dashboard-digest', [
            'company' => $company,
            'snapshot' => $snapshot,
            'statusOrder' => $statusOrder,
            'statusLabels' => $statusLabels,
        ])->render();

        return ['subject' => $subject, 'html' => $html];
    }

    /**
     * @return array{subject: string, html: string}
     */
    private function buildLeadSource(Company $company, ReportSchedule $schedule, ?Carbon $now): array
    {
        $period = $schedule->period ?? ReportSchedulePeriod::Yesterday;
        $range = $this->dashboard->periodRange($company->id, $period, $now);
        $groupBy = $schedule->group_by ?? LeadSourceGroupBy::VenueAndEvent;
        $rangeLabel = $this->dashboard->periodRangeLabel($range['start_local'], $range['end_local']);

        $report = $this->leadSource->report(
            $company->id,
            null,
            null,
            $range['start'],
            $range['end'],
            null,
            $groupBy,
        );

        $subject = sprintf(
            '%s — Performance by Lead Source %s (%s)',
            config('app.name'),
            $rangeLabel,
            $period->getLabel(),
        );

        $html = view('mail.lead-source-digest', [
            'company' => $company,
            'rangeLabel' => $rangeLabel,
            'periodLabel' => $period->getLabel(),
            'groupBy' => $groupBy,
            'totals' => $report['totals'],
            'rows' => $report['rows'],
            'leadSource' => $this->leadSource,
        ])->render();

        return ['subject' => $subject, 'html' => $html];
    }
}
