<?php

namespace App\Services\Leads;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Models\AppSetting;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Services\Dashboard\ManagerDashboardService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AgentStatsService
{
    /**
     * @return array<string, array{label: string, count: int, percent: ?float}>
     */
    public function scoreboardForUser(User $user, string $preset = 'today'): array
    {
        $dashboard = app(ManagerDashboardService::class);
        $timezone = $dashboard->companyTimezone($user->company_id);
        $dates = $dashboard->presetDates($preset, $timezone);
        $range = $dashboard->dateRange($user->company_id, $dates['start'], $dates['end']);
        $report = $dashboard->report($user->company_id, $user->id, null, $range['start'], $range['end']);

        return $report['totals'];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function scoreboardDatePresets(): array
    {
        return [
            ['key' => 'today', 'label' => 'Today'],
            ['key' => 'yesterday', 'label' => 'Yesterday'],
            ['key' => 'this_week', 'label' => 'This Week'],
            ['key' => 'last_week', 'label' => 'Last Week'],
            ['key' => 'mtd', 'label' => 'MTD'],
            ['key' => 'ytd', 'label' => 'YTD'],
        ];
    }

    /**
     * @return array{bookings: int, calls: int, skips: int, callbacks_pending: int, callable_remaining: int}
     */
    public function statsForUser(User $user): array
    {
        $range = $this->todayRange($user->company_id);

        $history = LeadHistory::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('actor_id', $user->id)
            ->whereBetween('occurred_at', [$range['start'], $range['end']]);

        $bookings = (clone $history)
            ->where('event_type', LeadHistoryType::Disposition)
            ->where('payload->disposition', Disposition::Booked->value)
            ->count();

        $calls = (clone $history)
            ->where('event_type', LeadHistoryType::Disposition)
            ->count();

        $skips = (clone $history)
            ->where('event_type', LeadHistoryType::Skip)
            ->count();

        $callbacksPending = Lead::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('status', LeadStatus::Callback)
            ->where('callback_owner_id', $user->id)
            ->count();

        $listIds = $user->listAssignments()->pluck('calling_list_id')->all();

        $callableRemaining = $listIds === []
            ? 0
            : Lead::withoutGlobalScopes()
                ->where('company_id', $user->company_id)
                ->where('status', LeadStatus::Callable)
                ->whereIn('calling_list_id', $listIds)
                ->count();

        return [
            'bookings' => $bookings,
            'calls' => $calls,
            'skips' => $skips,
            'callbacks_pending' => $callbacksPending,
            'callable_remaining' => $callableRemaining,
        ];
    }

    /**
     * @return Collection<int, array{user_id: int, name: string, bookings: int, calls: int, skips: int}>
     */
    public function leaderboard(int $companyId): Collection
    {
        $range = $this->todayRange($companyId);

        $history = LeadHistory::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('occurred_at', [$range['start'], $range['end']])
            ->whereNotNull('actor_id')
            ->get();

        $aggregated = [];

        foreach ($history as $row) {
            $actorId = (int) $row->actor_id;

            if (! isset($aggregated[$actorId])) {
                $aggregated[$actorId] = [
                    'user_id' => $actorId,
                    'bookings' => 0,
                    'calls' => 0,
                    'skips' => 0,
                ];
            }

            if ($row->event_type === LeadHistoryType::Skip) {
                $aggregated[$actorId]['skips']++;
            }

            if ($row->event_type === LeadHistoryType::Disposition) {
                $aggregated[$actorId]['calls']++;

                if (($row->payload['disposition'] ?? null) === Disposition::Booked->value) {
                    $aggregated[$actorId]['bookings']++;
                }
            }
        }

        $users = User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('id', array_keys($aggregated))
            ->get()
            ->keyBy('id');

        return collect($aggregated)
            ->map(function (array $row) use ($users) {
                $user = $users->get($row['user_id']);

                return [
                    'user_id' => $row['user_id'],
                    'name' => $user?->name ?? 'Unknown',
                    'bookings' => $row['bookings'],
                    'calls' => $row['calls'],
                    'skips' => $row['skips'],
                ];
            })
            ->sortByDesc('bookings')
            ->sortByDesc('calls')
            ->values();
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function todayRange(int $companyId): array
    {
        $settings = AppSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        $timezone = $settings?->dashboard_email_timezone ?? 'America/New_York';
        $start = Carbon::now($timezone)->startOfDay()->utc();
        $end = Carbon::now($timezone)->endOfDay()->utc();

        return ['start' => $start, 'end' => $end];
    }
}
