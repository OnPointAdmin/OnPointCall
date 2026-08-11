<?php

namespace App\Services\Dashboard;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
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
        $settings = AppSetting::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $timezone = $settings?->dashboard_email_timezone ?? 'America/New_York';
        $day ??= Carbon::now($timezone)->subDay();
        $start = $day->copy()->startOfDay()->utc();
        $end = $day->copy()->endOfDay()->utc();

        $stats = $this->aggregateStats($company->id, $start, $end);
        $agents = $this->dashboard->perAgentStatsForRange($company->id, $start, $end);

        $overdueCallbacks = Lead::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', LeadStatus::Callback)
            ->where('callback_at', '<', now())
            ->count();

        $subject = sprintf(
            '%s — Daily Dashboard %s',
            config('app.name'),
            $day->format('M j, Y'),
        );

        $html = view('mail.dashboard-digest', [
            'company' => $company,
            'day' => $day,
            'stats' => $stats,
            'agents' => $agents,
            'overdueCallbacks' => $overdueCallbacks,
        ])->render();

        return [
            'subject' => $subject,
            'html' => $html,
            'stats' => $stats,
        ];
    }

    /**
     * @return array{bookings: int, calls: int, skips: int}
     */
    private function aggregateStats(int $companyId, Carbon $start, Carbon $end): array
    {
        $history = LeadHistory::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('occurred_at', [$start, $end]);

        return [
            'bookings' => (clone $history)
                ->where('event_type', LeadHistoryType::Disposition)
                ->where('payload->disposition', Disposition::Booked->value)
                ->count(),
            'calls' => (clone $history)
                ->where('event_type', LeadHistoryType::Disposition)
                ->count(),
            'skips' => (clone $history)
                ->where('event_type', LeadHistoryType::Skip)
                ->count(),
        ];
    }
}
