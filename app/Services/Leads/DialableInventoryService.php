<?php

namespace App\Services\Leads;

use App\DataTransferObjects\DialableInventory;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Models\CallingList;
use App\Models\Lead;
use App\Models\LeadClaim;
use App\Models\LeadHistory;
use App\Services\Compliance\ComplianceService;
use App\Services\Compliance\DayPartResolver;
use App\Services\Dashboard\LeadDashboardService;
use App\Services\Dashboard\ManagerDashboardService;
use App\Support\CadenceDefaults;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DialableInventoryService
{
    /** @var array<int, array<int, DialableInventory>> */
    private array $byCompany = [];

    public function __construct(
        private readonly ComplianceService $compliance,
        private readonly LeadDashboardService $leadDashboard,
        private readonly ManagerDashboardService $managerDashboard,
        private readonly DayPartResolver $dayParts,
    ) {}

    public function forList(CallingList $list): DialableInventory
    {
        return $this->forCompany((int) $list->company_id)[$list->id] ?? DialableInventory::empty();
    }

    /**
     * @return array<int, DialableInventory>
     */
    public function forCompany(int $companyId): array
    {
        return $this->byCompany[$companyId] ??= $this->compute($companyId);
    }

    /**
     * Calling lists dialed today or with an active claim, with remaining inventory counts.
     *
     * @return list<array{list: CallingList, inventory: DialableInventory}>
     */
    public function activeTodayForCompany(int $companyId): array
    {
        $listIds = $this->activeTodayListIds($companyId);

        if ($listIds === []) {
            return [];
        }

        $lists = CallingList::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('id', $listIds)
            ->orderBy('name')
            ->get();

        $inventoryByList = $this->forCompany($companyId);
        $timezone = $this->managerDashboard->companyTimezone($companyId);

        $result = [];

        foreach ($lists as $list) {
            $inventory = ($inventoryByList[$list->id] ?? DialableInventory::empty())
                ->withTimezone($timezone);

            $result[] = [
                'list' => $list,
                'inventory' => $inventory,
            ];
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private function activeTodayListIds(int $companyId): array
    {
        $range = $this->managerDashboard->todayRange($companyId);

        $dialedTodayListIds = LeadHistory::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('occurred_at', [$range['start'], $range['end']])
            ->whereIn('event_type', [
                LeadHistoryType::Disposition->value,
                LeadHistoryType::Skip->value,
            ])
            ->whereHas('lead', fn ($query) => $query->whereNotNull('calling_list_id'))
            ->with('lead:id,calling_list_id')
            ->get()
            ->pluck('lead.calling_list_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $claimedListIds = LeadClaim::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('expires_at', '>', now())
            ->whereHas('lead', fn ($query) => $query->whereNotNull('calling_list_id'))
            ->with('lead:id,calling_list_id')
            ->get()
            ->pluck('lead.calling_list_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return array_values(array_unique(array_merge($dialedTodayListIds, $claimedListIds)));
    }

    /**
     * @return array<int, DialableInventory>
     */
    private function compute(int $companyId): array
    {
        $timezone = $this->managerDashboard->companyTimezone($companyId);

        $lists = CallingList::withoutGlobalScopes()
            ->with('cadence.dayParts')
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('id');

        $leads = Lead::withoutGlobalScopes()
            ->with([
                'callingList.cadence.dayParts',
                'callingList.cadence.attemptGaps',
                'claim',
            ])
            ->where('company_id', $companyId)
            ->whereIn('status', [LeadStatus::Callable, LeadStatus::Callback])
            ->get();

        /** @var array<int, array<string, mixed>> $buckets */
        $buckets = [];

        foreach ($leads as $lead) {
            $listId = (int) $lead->calling_list_id;

            if ($listId === 0) {
                continue;
            }

            if (! isset($buckets[$listId])) {
                $buckets[$listId] = $this->emptyBucket();
            }

            if ($lead->status === LeadStatus::Callback) {
                if ($lead->callback_at !== null && $lead->callback_at->lte(now())) {
                    $buckets[$listId]['callbacksDue']++;
                } else {
                    $buckets[$listId]['callbacksScheduled']++;
                }

                continue;
            }

            if (! $this->compliance->hasAttemptsRemaining($lead)) {
                $buckets[$listId]['maxAttempts']++;

                continue;
            }

            $claimed = $lead->claim !== null && $lead->claim->expires_at?->isFuture();

            if ($claimed) {
                $buckets[$listId]['claimed']++;

                continue;
            }

            if ($this->compliance->canDialNow($lead)) {
                $buckets[$listId]['readyNow']++;
                $readyPart = $this->cadenceDayPartKey($this->dayParts->currentDayPart($lead));
                $buckets[$listId]['readyNowByDayPart'][$readyPart]++;

                continue;
            }

            if (! $this->compliance->isWithinLegalWindow($lead)) {
                $buckets[$listId]['waitingHours']++;

                continue;
            }

            $buckets[$listId]['waitingCadence']++;
            $dayPart = $this->cadenceDayPartKey($lead->next_day_part);
            $buckets[$listId]['cadenceByDayPart'][$dayPart]++;

            $nextAt = $this->leadDashboard->nextDialableAt($lead);

            if ($nextAt === null) {
                $buckets[$listId]['cadenceWaitSlotCounts']['later'] = ($buckets[$listId]['cadenceWaitSlotCounts']['later'] ?? 0) + 1;
            } else {
                $local = $nextAt->copy()->timezone($timezone);
                $slotKey = $local->toDateString().'|'.$dayPart;
                $buckets[$listId]['cadenceWaitSlotCounts'][$slotKey] = ($buckets[$listId]['cadenceWaitSlotCounts'][$slotKey] ?? 0) + 1;
            }

            $existing = $buckets[$listId]['cadenceEarliestAt'][$dayPart];

            if ($nextAt !== null && ($existing === null || $nextAt->lt($existing))) {
                $buckets[$listId]['cadenceEarliestAt'][$dayPart] = $nextAt;
            }
        }

        $counts = [];

        foreach ($buckets as $listId => $bucket) {
            $waitingCadence = $bucket['waitingCadence'];
            $waitingHours = $bucket['waitingHours'];
            $claimed = $bucket['claimed'];
            $waiting = $waitingCadence + $waitingHours + $claimed;

            $cadenceEarliestByPart = [];

            foreach ($bucket['cadenceEarliestAt'] as $part => $at) {
                $cadenceEarliestByPart[$part] = DialableInventory::formatEarliestDialable(
                    $at instanceof CarbonInterface ? $at : null,
                    $timezone,
                );
            }

            $counts[$listId] = new DialableInventory(
                readyNow: $bucket['readyNow'],
                waiting: $waiting,
                waitingCadence: $waitingCadence,
                waitingHours: $waitingHours,
                claimed: $claimed,
                maxAttempts: $bucket['maxAttempts'],
                cadenceByDayPart: $bucket['cadenceByDayPart'],
                callbacksDue: $bucket['callbacksDue'],
                callbacksScheduled: $bucket['callbacksScheduled'],
                cadenceEarliestByPart: $cadenceEarliestByPart,
                cadenceWaitSlots: $this->cadenceWaitSlotRows(
                    $bucket,
                    $timezone,
                    $this->cadenceWindowStarts($lists->get($listId)),
                ),
                timezone: $timezone,
                readyNowByDayPart: $bucket['readyNowByDayPart'],
            );
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @param  array<string, string>  $windowStarts
     * @return list<array{label: string, count: int}>
     */
    private function cadenceWaitSlotRows(array $bucket, string $timezone, array $windowStarts): array
    {
        if ($bucket['waitingCadence'] === 0) {
            return [];
        }

        $today = Carbon::now($timezone)->toDateString();
        $tomorrow = Carbon::now($timezone)->copy()->addDay()->toDateString();
        $partOrder = array_flip([...CadenceDefaults::DAY_PARTS, 'other']);
        $rows = [];
        $afterTomorrowByPart = [];
        $unscheduled = $bucket['cadenceWaitSlotCounts']['later'] ?? 0;

        foreach ($bucket['cadenceWaitSlotCounts'] as $key => $count) {
            if ($key === 'later' || $count === 0) {
                continue;
            }

            [$date, $dayPart] = array_pad(explode('|', $key, 2), 2, 'other');
            $dayPart = $this->cadenceDayPartKey($dayPart);
            $windowStart = $windowStarts[$dayPart] ?? '00:00';

            if ($date === $today || $date === $tomorrow) {
                $rows[] = [
                    'label' => DialableInventory::formatCadenceWindowLabel(
                        $date,
                        $dayPart,
                        $windowStart,
                        $timezone,
                    ),
                    'count' => $count,
                    'date' => $date,
                    'start' => $windowStart,
                    'part' => $dayPart,
                ];

                continue;
            }

            $afterTomorrowByPart[$dayPart] = ($afterTomorrowByPart[$dayPart] ?? 0) + $count;
        }

        foreach ($afterTomorrowByPart as $dayPart => $count) {
            $rows[] = [
                'label' => $this->afterTomorrowPartLabel($dayPart, $timezone, $windowStarts),
                'count' => $count,
                'date' => '9999-12-31',
                'start' => $windowStarts[$dayPart] ?? '99:99',
                'part' => $dayPart,
            ];
        }

        if ($unscheduled > 0) {
            $rows[] = [
                'label' => 'After tomorrow',
                'count' => $unscheduled,
                'date' => '9999-12-31',
                'start' => '99:99',
                'part' => 'other',
            ];
        }

        usort(
            $rows,
            function (array $left, array $right) use ($partOrder): int {
                return [$left['date'], $left['start'], $partOrder[$left['part']] ?? 99]
                    <=> [$right['date'], $right['start'], $partOrder[$right['part']] ?? 99];
            },
        );

        return array_map(
            fn (array $row): array => [
                'label' => $row['label'],
                'count' => $row['count'],
            ],
            $rows,
        );
    }

    /**
     * @param  array<string, string>  $windowStarts
     */
    private function afterTomorrowPartLabel(string $dayPart, string $timezone, array $windowStarts): string
    {
        if ($dayPart === 'other' || ! isset($windowStarts[$dayPart])) {
            return 'After tomorrow';
        }

        $local = Carbon::parse(
            Carbon::now($timezone)->toDateString().' '.$windowStarts[$dayPart],
            $timezone,
        );

        return 'After tomorrow, '.CadenceDefaults::label($dayPart).' · '.$local->format('g:i A T');
    }

    /**
     * @return array<string, string>
     */
    private function cadenceWindowStarts(?CallingList $list): array
    {
        $starts = [];

        foreach (CadenceDefaults::windows() as $part => [$start]) {
            $starts[$part] = $start;
        }

        foreach ($this->enabledCadenceDayParts($list) as $part) {
            $starts[$part['day_part']] = $part['window_start'];
        }

        return $starts;
    }

    /**
     * @return list<array{day_part: string, window_start: string}>
     */
    private function enabledCadenceDayParts(?CallingList $list): array
    {
        $dayParts = $list?->cadence?->dayParts
            ?->where('enabled', true)
            ->sortBy('rotation_order')
            ->values();

        if ($dayParts === null || $dayParts->isEmpty()) {
            return array_map(
                fn (array $row): array => [
                    'day_part' => $row['day_part'],
                    'window_start' => $row['window_start'],
                ],
                array_filter(
                    CadenceDefaults::dayPartRows(),
                    fn (array $row): bool => $row['enabled'],
                ),
            );
        }

        $parts = [];

        foreach ($dayParts as $part) {
            $parts[] = [
                'day_part' => $part->day_part,
                'window_start' => $this->formatWindowTime($part->window_start),
            ];
        }

        return $parts;
    }

    private function formatWindowTime(mixed $time): string
    {
        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        $value = trim((string) $time);

        if (preg_match('/(\d{1,2}):(\d{2})/', $value, $matches) === 1) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBucket(): array
    {
        return [
            'readyNow' => 0,
            'waitingCadence' => 0,
            'waitingHours' => 0,
            'claimed' => 0,
            'maxAttempts' => 0,
            'cadenceByDayPart' => [
                'morning' => 0,
                'afternoon' => 0,
                'evening' => 0,
                'other' => 0,
            ],
            'cadenceEarliestAt' => [
                'morning' => null,
                'afternoon' => null,
                'evening' => null,
                'other' => null,
            ],
            'cadenceWaitSlotCounts' => [],
            'callbacksDue' => 0,
            'callbacksScheduled' => 0,
            'readyNowByDayPart' => [
                'morning' => 0,
                'afternoon' => 0,
                'evening' => 0,
                'other' => 0,
            ],
        ];
    }

    private function cadenceDayPartKey(?string $nextDayPart): string
    {
        if ($nextDayPart === null || ! in_array($nextDayPart, CadenceDefaults::DAY_PARTS, true)) {
            return 'other';
        }

        return $nextDayPart;
    }
}
