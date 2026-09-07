<?php

namespace App\Services\Dashboard;

use App\Enums\ReportSchedulePeriod;
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
            'subtitle' => $day->format('l, F j, Y'),
            'periodLabel' => null,
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

    /**
     * @return array{subject: string, html: string, stats: array<string, int>}
     */
    public function buildForPeriod(Company $company, ReportSchedulePeriod $period, ?Carbon $now = null): array
    {
        $timezone = $this->dashboard->companyTimezone($company->id);
        $now = ($now ?? Carbon::now($timezone))->copy()->timezone($timezone);
        $range = $this->dashboard->periodRange($company->id, $period, $now);
        $rangeLabel = $this->dashboard->periodRangeLabel($range['start_local'], $range['end_local']);
        $report = $this->dashboard->report(
            $company->id,
            null,
            null,
            $range['start'],
            $range['end'],
        );

        $subject = sprintf(
            '%s — Agent Dashboard %s (%s)',
            config('app.name'),
            $rangeLabel,
            $period->getLabel(),
        );

        $html = view('mail.dashboard-digest', [
            'company' => $company,
            'day' => $range['end_local'],
            'subtitle' => $rangeLabel,
            'periodLabel' => $period->getLabel(),
            'totals' => $report['totals'],
            'breakdowns' => $report['breakdowns'] ?? [],
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
