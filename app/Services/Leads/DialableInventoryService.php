<?php

namespace App\Services\Leads;

use App\DataTransferObjects\DialableInventory;
use App\Enums\LeadStatus;
use App\Models\CallingList;
use App\Models\Lead;
use App\Services\Compliance\ComplianceService;
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
                    $this->cadenceWindowTemplate($lists->get($listId), $timezone),
                ),
                timezone: $timezone,
            );
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @param  list<array{key: string, label: string}>  $templateSlots
     * @return list<array{label: string, count: int}>
     */
    private function cadenceWaitSlotRows(array $bucket, string $timezone, array $templateSlots): array
    {
        if ($bucket['waitingCadence'] === 0) {
            return [];
        }

        $afterTomorrow = $bucket['cadenceWaitSlotCounts']['later'] ?? 0;

        foreach ($bucket['cadenceWaitSlotCounts'] as $key => $count) {
            if ($key === 'later' || $count === 0) {
                continue;
            }

            if (! in_array($key, array_column($templateSlots, 'key'), true)) {
                $afterTomorrow += $count;
            }
        }

        $rows = [];

        foreach ($templateSlots as $slot) {
            $rows[] = [
                'label' => $slot['label'],
                'count' => $bucket['cadenceWaitSlotCounts'][$slot['key']] ?? 0,
            ];
        }

        if ($afterTomorrow > 0) {
            $rows[] = [
                'label' => 'After tomorrow',
                'count' => $afterTomorrow,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function cadenceWindowTemplate(?CallingList $list, string $timezone): array
    {
        $now = Carbon::now($timezone);
        $dates = [
            $now->toDateString(),
            $now->copy()->addDay()->toDateString(),
        ];

        $slots = [];

        foreach ($this->enabledCadenceDayParts($list) as $part) {
            foreach ($dates as $date) {
                $windowStart = Carbon::parse($date.' '.$part['window_start'], $timezone);

                if ($windowStart->lte($now)) {
                    continue;
                }

                $key = $date.'|'.$part['day_part'];
                $slots[] = [
                    'key' => $key,
                    'label' => DialableInventory::formatCadenceWindowLabel(
                        $date,
                        $part['day_part'],
                        $part['window_start'],
                        $timezone,
                    ),
                ];
            }
        }

        return $slots;
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
