<?php

namespace App\Services\Dashboard;

use App\DataTransferObjects\LeadDashboardSnapshot;
use App\Enums\LeadStatus;
use App\Models\CallingList;
use App\Models\Lead;
use App\Services\Compliance\AttemptGapResolver;
use App\Services\Compliance\ComplianceService;
use App\Services\Compliance\DayPartResolver;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class LeadDashboardService
{
    private const FORECAST_DAYS = 14;

    /**
     * @var list<array{key: string, label: string}>
     */
    public const FORECAST_BUCKETS = [
        ['key' => 'ready_now', 'label' => 'Ready now'],
        ['key' => 'next_hour', 'label' => 'Next hour'],
        ['key' => 'later_today', 'label' => 'Later today'],
        ['key' => 'tomorrow', 'label' => 'Tomorrow'],
        ['key' => 'next_7_days', 'label' => 'Next 7 days'],
        ['key' => 'later', 'label' => 'Later'],
    ];

    public function __construct(
        private readonly ComplianceService $compliance,
        private readonly AttemptGapResolver $attemptGaps,
        private readonly DayPartResolver $dayParts,
        private readonly ManagerDashboardService $managerDashboard,
    ) {}

    public function snapshot(int $companyId): LeadDashboardSnapshot
    {
        $timezone = $this->managerDashboard->companyTimezone($companyId);
        $statusCounts = $this->statusCounts($companyId);
        $callbackCounts = $this->callbackCounts($companyId);
        $pool = $this->callablePool($companyId);

        $readyNow = 0;
        $waiting = 0;
        $claimed = 0;
        $exhausted = 0;
        $forecastCounts = array_fill_keys(array_column(self::FORECAST_BUCKETS, 'key'), 0);
        $byListReady = [];
        $byListWaiting = [];

        foreach ($pool as $lead) {
            $listId = (int) $lead->calling_list_id;

            if (! $this->compliance->hasAttemptsRemaining($lead)) {
                $exhausted++;

                continue;
            }

            $isClaimed = $lead->claim !== null && $lead->claim->expires_at?->isFuture();

            if ($isClaimed) {
                $claimed++;
            }

            if (! $isClaimed && $this->compliance->canDialNow($lead)) {
                $readyNow++;
                $forecastCounts['ready_now']++;
                $byListReady[$listId] = ($byListReady[$listId] ?? 0) + 1;

                continue;
            }

            $waiting++;
            $byListWaiting[$listId] = ($byListWaiting[$listId] ?? 0) + 1;
            $forecastCounts[$this->forecastBucket($this->nextDialableAt($lead), $timezone)]++;
        }

        $forecast = [];

        foreach (self::FORECAST_BUCKETS as $bucket) {
            $forecast[] = [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'count' => $forecastCounts[$bucket['key']] ?? 0,
            ];
        }

        return new LeadDashboardSnapshot(
            total: array_sum($statusCounts),
            fresh: $this->freshCount($companyId),
            statusCounts: $statusCounts,
            readyNow: $readyNow,
            waiting: $waiting,
            claimed: $claimed,
            exhausted: $exhausted,
            callbacksDue: $callbackCounts['due'],
            callbacksScheduled: $callbackCounts['scheduled'],
            forecast: $forecast,
            byList: $this->byList($companyId, $byListReady, $byListWaiting),
            timezone: $timezone,
            runAt: Carbon::now($timezone)->format('M j, Y g:i A'),
        );
    }

    public function nextDialableAt(Lead $lead, ?CarbonInterface $from = null): ?CarbonInterface
    {
        $from ??= now();
        $notBefore = $from->copy();

        $claimExpires = $lead->claim?->expires_at;

        if ($claimExpires?->isFuture()) {
            $notBefore = $this->later($notBefore, $claimExpires);
        }

        $notBefore = $this->later($notBefore, $this->attemptGaps->eligibleAt($lead));

        $parts = $this->dayParts->dayPartsFor($lead);

        if ($lead->next_day_part !== null && in_array($lead->next_day_part, $parts, true)) {
            $notBefore = $this->later($notBefore, $this->dayParts->waitAfterEligibleAt($lead));
        }

        if ($this->compliance->canDialNow($lead, $notBefore)) {
            return $notBefore;
        }

        $windows = $this->dayParts->eligibleWindows($lead);
        $tz = $lead->timezone ?: 'America/New_York';
        $localFrom = $notBefore->copy()->timezone($tz);

        for ($day = 0; $day <= self::FORECAST_DAYS; $day++) {
            $date = $localFrom->copy()->startOfDay()->addDays($day);
            $legal = $this->compliance->legalWindowOnLocalDate($lead, $date);

            if ($legal === null) {
                continue;
            }

            $dateString = $date->toDateString();

            foreach ($windows as [$start, $end]) {
                $slotStart = $this->laterTime($start, $legal['start']);
                $slotEnd = $this->earlierTime($end, $legal['end']);

                if ($slotStart >= $slotEnd) {
                    continue;
                }

                $slotStartAt = $this->atLocal($tz, $dateString, $slotStart);
                $slotEndAt = $this->atLocal($tz, $dateString, $slotEnd);
                $candidate = $slotStartAt->gt($notBefore) ? $slotStartAt : $notBefore->copy();

                if ($candidate->gte($slotEndAt)) {
                    continue;
                }

                if ($this->compliance->canDialNow($lead, $candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(int $companyId): array
    {
        $counts = [];

        foreach (LeadStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        $rows = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->selectRaw('status, count(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        foreach ($rows as $status => $count) {
            $key = $status instanceof LeadStatus ? $status->value : (string) $status;
            $counts[$key] = (int) $count;
        }

        return $counts;
    }

    private function freshCount(int $companyId): int
    {
        return Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Callable)
            ->where('attempt_count', 0)
            ->count();
    }

    /**
     * @return array{due: int, scheduled: int}
     */
    private function callbackCounts(int $companyId): array
    {
        $due = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Callback)
            ->where('callback_at', '<=', now())
            ->count();

        $scheduled = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Callback)
            ->where(function ($query): void {
                $query->whereNull('callback_at')
                    ->orWhere('callback_at', '>', now());
            })
            ->count();

        return [
            'due' => $due,
            'scheduled' => $scheduled,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Lead>
     */
    private function callablePool(int $companyId)
    {
        return Lead::withoutGlobalScopes()
            ->with([
                'callingList.cadence.dayParts',
                'callingList.cadence.attemptGaps',
                'claim',
            ])
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Callable)
            ->get();
    }

    /**
     * @param  array<int, int>  $ready
     * @param  array<int, int>  $waiting
     * @return list<array{name: string, total: int, holding: int, ready_now: int, waiting: int, callbacks: int}>
     */
    private function byList(int $companyId, array $ready, array $waiting): array
    {
        $lists = CallingList::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $grouped = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->selectRaw('calling_list_id, status, count(*) as aggregate_count')
            ->groupBy('calling_list_id', 'status')
            ->get();

        $byListStatus = [];

        foreach ($grouped as $row) {
            $listId = (int) ($row->calling_list_id ?? 0);
            $status = $row->status instanceof LeadStatus
                ? $row->status->value
                : (string) $row->status;
            $byListStatus[$listId][$status] = (int) $row->aggregate_count;
        }

        $rows = [];

        foreach ($lists as $list) {
            $statusCounts = $byListStatus[$list->id] ?? [];
            $rows[] = $this->listRow(
                $list->name,
                $statusCounts,
                $ready[$list->id] ?? 0,
                $waiting[$list->id] ?? 0,
            );
        }

        if (($byListStatus[0] ?? []) !== []) {
            $rows[] = $this->listRow(
                'Unassigned',
                $byListStatus[0],
                $ready[0] ?? 0,
                $waiting[0] ?? 0,
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $statusCounts
     * @return array{name: string, total: int, holding: int, ready_now: int, waiting: int, callbacks: int}
     */
    private function listRow(string $name, array $statusCounts, int $readyNow, int $waiting): array
    {
        return [
            'name' => $name,
            'total' => array_sum($statusCounts),
            'holding' => $statusCounts[LeadStatus::Holding->value] ?? 0,
            'ready_now' => $readyNow,
            'waiting' => $waiting,
            'callbacks' => $statusCounts[LeadStatus::Callback->value] ?? 0,
        ];
    }

    private function forecastBucket(?CarbonInterface $at, string $timezone): string
    {
        if ($at === null) {
            return 'later';
        }

        $now = Carbon::now($timezone);
        $local = $at->copy()->timezone($timezone);

        if ($local->lte($now)) {
            return 'ready_now';
        }

        if ($local->lte($now->copy()->addHour())) {
            return 'next_hour';
        }

        if ($local->toDateString() === $now->toDateString()) {
            return 'later_today';
        }

        if ($local->toDateString() === $now->copy()->addDay()->toDateString()) {
            return 'tomorrow';
        }

        if ($local->lte($now->copy()->addDays(7))) {
            return 'next_7_days';
        }

        return 'later';
    }

    private function later(CarbonInterface $current, ?CarbonInterface $other): CarbonInterface
    {
        if ($other === null) {
            return $current;
        }

        return $other->gt($current) ? $other->copy() : $current;
    }

    private function laterTime(string $a, string $b): string
    {
        $a = substr($a, 0, 5);
        $b = substr($b, 0, 5);

        return $a >= $b ? $a : $b;
    }

    private function earlierTime(string $a, string $b): string
    {
        $a = substr($a, 0, 5);
        $b = substr($b, 0, 5);

        return $a <= $b ? $a : $b;
    }

    private function atLocal(string $timezone, string $date, string $time): CarbonInterface
    {
        $time = strlen($time) >= 8 ? substr($time, 0, 8) : substr($time, 0, 5).':00';

        return Carbon::parse($date.' '.$time, $timezone)->utc();
    }
}
