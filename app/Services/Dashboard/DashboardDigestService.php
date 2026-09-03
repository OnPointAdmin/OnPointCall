<?php

namespace App\Services\Dashboard;

use App\Models\Company;
use Carbon\Carbon;

class DashboardDigestService
{
    public function __construct(
        private readonly ManagerDashboardService $dashboard,
    ) {}

    /**
     * @return array{subject: string, html: string, stats: array<string, int>}
     */
    public function buildForCompany(Company $company, ?Carbon $day = null): array
    {
        $timezone = $this->dashboard->companyTimezone($company->id);
        $day ??= Carbon::now($timezone)->subDay();
        $range = $this->dashboard->dateRange($company->id, $day, $day);
        $report = $this->dashboard->report(
            $company->id,
            null,
            null,
            $range['start'],
            $range['end'],
        );

        $subject = sprintf(
            '%s — Daily Dashboard %s',
            config('app.name'),
            $day->format('M j, Y'),
        );

        $html = view('mail.dashboard-digest', [
            'company' => $company,
            'day' => $day,
            'totals' => $report['totals'],
            'agents' => $report['agents'],
            'metricDefinitions' => $this->dashboard->metricDefinitions(),
            'dashboard' => $this->dashboard,
        ])->render();

        $stats = [];

        foreach ($report['totals'] as $key => $metric) {
            $stats[$key] = $metric['count'];
        }

        return [
            'subject' => $subject,
            'html' => $html,
            'stats' => $stats,
        ];
    }
}
