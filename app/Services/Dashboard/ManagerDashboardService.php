<?php

namespace App\Services\Dashboard;

use App\Enums\Disposition;
use App\Enums\DispositionReportGroup;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\DispositionDefinition;
use App\Models\AppSetting;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Support\CompanyTimezone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ManagerDashboardService
{
    /**
     * @return array{bookings: int, calls: int, skips: int, overdue_callbacks: int, orphaned_callbacks: int}
     */
    public function todayStats(int $companyId): array
    {
        $range = $this->todayRange($companyId);

        $history = LeadHistory::withoutGlobalScopes()
            ->where('company_id', $companyId)
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

        $overdueCallbacks = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Callback)
            ->where('callback_at', '<', now())
            ->count();

        $orphanedCallbacks = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Callback)
            ->where(function ($query): void {
                $query->whereNull('callback_owner_id')
                    ->orWhereHas('callbackOwner', fn ($owner) => $owner->where('active', false));
            })
            ->count();

        return [
            'bookings' => $bookings,
            'calls' => $calls,
            'skips' => $skips,
            'overdue_callbacks' => $overdueCallbacks,
            'orphaned_callbacks' => $orphanedCallbacks,
        ];
    }

    /**
     * @return array{totals: array<string, array{label: string, count: int, percent: ?float}>, agents: list<array{user_id: int, name: string, metrics: array<string, array{count: int, percent: ?float}>}>}
     */
    public function report(
        int $companyId,
        ?int $actorId,
        ?string $leadType,
        Carbon $start,
        Carbon $end,
        int|string|null $callingListId = null,
    ): array {
        $history = $this->historyQuery($companyId, $actorId, $leadType, $start, $end, $callingListId)->get();
        $overdueByOwner = $this->overdueCallbacksByOwner($companyId, $actorId, $leadType, $callingListId);

        $agentBuckets = [];
        $totals = $this->emptyMetrics();

        $reportGroupMap = $this->reportGroupMap($companyId);

        foreach ($history as $row) {
            if ($row->actor_id === null) {
                continue;
            }

            $actorKey = (int) $row->actor_id;

            if (! isset($agentBuckets[$actorKey])) {
                $agentBuckets[$actorKey] = $this->emptyMetrics();
            }

            $this->applyHistoryRow($totals, $row, $reportGroupMap);
            $this->applyHistoryRow($agentBuckets[$actorKey], $row, $reportGroupMap);
        }

        $totals['overdue_callbacks']['count'] = array_sum($overdueByOwner);
        $this->applyPercents($totals);

        $users = User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('id');

        $agentIds = $this->resolveAgentIds($companyId, $actorId, array_keys($agentBuckets), array_keys($overdueByOwner));

        $agents = [];

        foreach ($agentIds as $agentId) {
            $metrics = $agentBuckets[$agentId] ?? $this->emptyMetrics();
            $metrics['overdue_callbacks']['count'] = $overdueByOwner[$agentId] ?? 0;
            $this->applyPercents($metrics);

            $agents[] = [
                'user_id' => $agentId,
                'name' => $users->get($agentId)?->name ?? 'Unknown',
                'metrics' => $metrics,
            ];
        }

        usort($agents, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return [
            'totals' => $totals,
            'agents' => $agents,
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function priorDayRange(int $companyId): array
    {
        $settings = AppSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        $timezone = $this->timezone($settings);
        $start = Carbon::now($timezone)->subDay()->startOfDay()->utc();
        $end = Carbon::now($timezone)->subDay()->endOfDay()->utc();

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function todayRange(int $companyId): array
    {
        $settings = AppSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        $timezone = $this->timezone($settings);
        $start = Carbon::now($timezone)->startOfDay()->utc();
        $end = Carbon::now($timezone)->endOfDay()->utc();

        return ['start' => $start, 'end' => $end];
    }

    public function companyTimezone(int $companyId): string
    {
        return CompanyTimezone::for($companyId);
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function dateRange(int $companyId, Carbon $startDate, Carbon $endDate): array
    {
        $timezone = $this->companyTimezone($companyId);
        $start = Carbon::parse($startDate->toDateString(), $timezone)->startOfDay()->utc();
        $end = Carbon::parse($endDate->toDateString(), $timezone)->endOfDay()->utc();

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Weeks run Monday through Sunday.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function presetDates(string $preset, string $timezone, ?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now($timezone))->copy()->timezone($timezone);
        $today = $now->copy()->startOfDay();

        return match ($preset) {
            'yesterday' => [
                'start' => $today->copy()->subDay(),
                'end' => $today->copy()->subDay(),
            ],
            'this_week' => [
                'start' => $today->copy()->startOfWeek(Carbon::MONDAY),
                'end' => $today->copy(),
            ],
            'last_week' => [
                'start' => $today->copy()->subWeek()->startOfWeek(Carbon::MONDAY),
                'end' => $today->copy()->subWeek()->endOfWeek(Carbon::SUNDAY)->startOfDay(),
            ],
            'mtd' => [
                'start' => $today->copy()->startOfMonth(),
                'end' => $today->copy(),
            ],
            'ytd' => [
                'start' => $today->copy()->startOfYear(),
                'end' => $today->copy(),
            ],
            default => [
                'start' => $today->copy(),
                'end' => $today->copy(),
            ],
        };
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function datePresets(): array
    {
        return [
            ['key' => 'today', 'label' => 'Today'],
            ['key' => 'this_week', 'label' => 'This Week'],
            ['key' => 'last_week', 'label' => 'Last Week'],
            ['key' => 'mtd', 'label' => 'MTD'],
            ['key' => 'ytd', 'label' => 'YTD'],
        ];
    }

    /**
     * @return array<int, array{user_id: int, name: string, bookings: int, calls: int, skips: int, callbacks_pending: int}>
     */
    public function perAgentStatsForRange(int $companyId, Carbon $start, Carbon $end): array
    {
        $history = LeadHistory::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('occurred_at', [$start, $end])
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
            ->get()
            ->keyBy('id');

        $result = [];

        foreach ($aggregated as $actorId => $row) {
            $user = $users->get($actorId);

            $callbacksPending = Lead::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('status', LeadStatus::Callback)
                ->where('callback_owner_id', $actorId)
                ->count();

            $result[] = [
                'user_id' => $actorId,
                'name' => $user?->name ?? 'Unknown',
                'bookings' => $row['bookings'],
                'calls' => $row['calls'],
                'skips' => $row['skips'],
                'callbacks_pending' => $callbacksPending,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{key: string, label: string, show_percent: bool}>
     */
    public function metricDefinitions(): array
    {
        return [
            ['key' => 'total_leads_called', 'label' => 'Total Leads Called', 'show_percent' => false],
            ['key' => 'booked', 'label' => 'Booked', 'show_percent' => true],
            ['key' => 'not_interested', 'label' => 'Not Interested', 'show_percent' => true],
            ['key' => 'not_qualified', 'label' => 'Not Qualified', 'show_percent' => true],
            ['key' => 'no_answer_vm', 'label' => 'No Answer / VM', 'show_percent' => true],
            ['key' => 'wrong_dnc', 'label' => 'Wrong / DNC', 'show_percent' => true],
            ['key' => 'skipped', 'label' => 'Skipped', 'show_percent' => true],
            ['key' => 'callbacks', 'label' => 'Call Backs', 'show_percent' => true],
            ['key' => 'other', 'label' => 'Other', 'show_percent' => true],
            ['key' => 'overdue_callbacks', 'label' => 'Overdue Call Backs', 'show_percent' => true],
        ];
    }

    /**
     * @param  array<string, array{label: string, count: int, percent: ?float}>  $metrics
     */
    public function formatPercent(array $metrics, string $key): string
    {
        $percent = $metrics[$key]['percent'] ?? null;

        return $percent === null ? '—' : number_format($percent, 1).'%';
    }

    /**
     * @return array<string, array{label: string, count: int, percent: ?float}>
     */
    private function emptyMetrics(): array
    {
        $metrics = [];

        foreach ($this->metricDefinitions() as $definition) {
            $metrics[$definition['key']] = [
                'label' => $definition['label'],
                'count' => 0,
                'percent' => null,
            ];
        }

        return $metrics;
    }

    private function applyHistoryRow(array &$metrics, LeadHistory $row, array $reportGroupMap): void
    {
        if ($row->event_type === LeadHistoryType::Skip) {
            $metrics['skipped']['count']++;
            $metrics['total_leads_called']['count']++;

            return;
        }

        if ($row->event_type !== LeadHistoryType::Disposition) {
            return;
        }

        $metrics['total_leads_called']['count']++;

        $slug = (string) ($row->payload['disposition'] ?? '');
        $group = $reportGroupMap[$slug] ?? DispositionReportGroup::Other->value;

        match ($group) {
            DispositionReportGroup::Booked->value => $metrics['booked']['count']++,
            DispositionReportGroup::NotInterested->value => $metrics['not_interested']['count']++,
            DispositionReportGroup::NotQualified->value => $metrics['not_qualified']['count']++,
            DispositionReportGroup::NoAnswerVm->value => $metrics['no_answer_vm']['count']++,
            DispositionReportGroup::WrongDnc->value => $metrics['wrong_dnc']['count']++,
            DispositionReportGroup::Callbacks->value => $metrics['callbacks']['count']++,
            DispositionReportGroup::Other->value => $metrics['other']['count']++,
            default => $metrics['other']['count']++,
        };
    }

    /**
     * @return array<string, string>
     */
    private function reportGroupMap(int $companyId): array
    {
        return DispositionDefinition::indexedForCompany($companyId)
            ->mapWithKeys(fn (DispositionDefinition $definition): array => [
                $definition->slug => $definition->report_group->value,
            ])
            ->all();
    }

    /**
     * @param  array<string, array{label: string, count: int, percent: ?float}>  $metrics
     */
    private function applyPercents(array &$metrics): void
    {
        $total = $metrics['total_leads_called']['count'];

        foreach ($metrics as $key => &$metric) {
            if ($key === 'total_leads_called' || $key === 'overdue_callbacks') {
                $metric['percent'] = null;

                continue;
            }

            $metric['percent'] = $total > 0
                ? round(($metric['count'] / $total) * 100, 1)
                : null;
        }
    }

    /**
     * @param  list<int>  $activityAgentIds
     * @param  list<int|string|null>  $overdueOwnerIds
     * @return list<int>
     */
    private function resolveAgentIds(int $companyId, ?int $actorId, array $activityAgentIds, array $overdueOwnerIds): array
    {
        if ($actorId !== null) {
            return [$actorId];
        }

        $ids = collect($activityAgentIds)
            ->merge($overdueOwnerIds)
            ->filter(fn (mixed $id): bool => $id !== null && $id !== '')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $validAgentIds = User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('role', UserRole::Agent)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);

        return $validAgentIds->sort()->values()->all();
    }

    /**
     * @return array<int, int>
     */
    private function overdueCallbacksByOwner(
        int $companyId,
        ?int $actorId,
        ?string $leadType,
        int|string|null $callingListId = null,
    ): array {
        $query = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Callback)
            ->where('callback_at', '<', now());

        if ($actorId !== null) {
            $query->where('callback_owner_id', $actorId);
        }

        if ($leadType !== null) {
            $query->where('lead_type', $leadType);
        }

        $this->constrainCallingList($query, $callingListId);

        return $query
            ->selectRaw('callback_owner_id, count(*) as aggregate_count')
            ->groupBy('callback_owner_id')
            ->pluck('aggregate_count', 'callback_owner_id')
            ->mapWithKeys(fn (mixed $count, mixed $ownerId): array => [(int) $ownerId => (int) $count])
            ->all();
    }

    private function historyQuery(
        int $companyId,
        ?int $actorId,
        ?string $leadType,
        Carbon $start,
        Carbon $end,
        int|string|null $callingListId = null,
    ) {
        $query = LeadHistory::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('occurred_at', [$start, $end])
            ->whereIn('event_type', [
                LeadHistoryType::Disposition->value,
                LeadHistoryType::Skip->value,
            ]);

        if ($actorId !== null) {
            $query->where('actor_id', $actorId);
        }

        if ($leadType !== null || $this->hasCallingListFilter($callingListId)) {
            $query->whereHas('lead', function ($leadQuery) use ($leadType, $callingListId): void {
                if ($leadType !== null) {
                    $leadQuery->where('lead_type', $leadType);
                }

                $this->constrainCallingList($leadQuery, $callingListId);
            });
        }

        return $query;
    }

    private function hasCallingListFilter(int|string|null $callingListId): bool
    {
        return $callingListId !== null && $callingListId !== '';
    }

    /**
     * @param  Builder<Lead>  $query
     */
    private function constrainCallingList(Builder $query, int|string|null $callingListId): void
    {
        if (! $this->hasCallingListFilter($callingListId)) {
            return;
        }

        if ($callingListId === 'holding') {
            $query->whereNull('calling_list_id');

            return;
        }

        $query->where('calling_list_id', (int) $callingListId);
    }

    private function timezone(?AppSetting $settings): string
    {
        return CompanyTimezone::normalize($settings?->dashboard_email_timezone);
    }
}
