<?php

namespace App\DataTransferObjects;

use App\Support\CadenceDefaults;
use Carbon\Carbon;
use Carbon\CarbonInterface;

readonly class DialableInventory
{
    /**
     * @param  array{morning: int, afternoon: int, evening: int, other: int}  $cadenceByDayPart
     * @param  array{morning: ?string, afternoon: ?string, evening: ?string, other: ?string}  $cadenceEarliestByPart
     * @param  list<array{label: string, count: int}>  $cadenceWaitSlots
     * @param  array{morning: int, afternoon: int, evening: int, other: int}  $readyNowByDayPart
     */
    public function __construct(
        public int $readyNow,
        public int $waiting,
        public int $waitingCadence = 0,
        public int $waitingHours = 0,
        public int $claimed = 0,
        public int $maxAttempts = 0,
        public array $cadenceByDayPart = [
            'morning' => 0,
            'afternoon' => 0,
            'evening' => 0,
            'other' => 0,
        ],
        public int $callbacksDue = 0,
        public int $callbacksScheduled = 0,
        public array $cadenceEarliestByPart = [
            'morning' => null,
            'afternoon' => null,
            'evening' => null,
            'other' => null,
        ],
        public array $cadenceWaitSlots = [],
        public ?string $timezone = null,
        public array $readyNowByDayPart = [
            'morning' => 0,
            'afternoon' => 0,
            'evening' => 0,
            'other' => 0,
        ],
    ) {}

    public static function empty(?string $timezone = null): self
    {
        return new self(0, 0, timezone: $timezone);
    }

    public function withTimezone(string $timezone): self
    {
        if ($this->timezone === $timezone) {
            return $this;
        }

        return new self(
            readyNow: $this->readyNow,
            waiting: $this->waiting,
            waitingCadence: $this->waitingCadence,
            waitingHours: $this->waitingHours,
            claimed: $this->claimed,
            maxAttempts: $this->maxAttempts,
            cadenceByDayPart: $this->cadenceByDayPart,
            callbacksDue: $this->callbacksDue,
            callbacksScheduled: $this->callbacksScheduled,
            cadenceEarliestByPart: $this->cadenceEarliestByPart,
            cadenceWaitSlots: $this->cadenceWaitSlots,
            timezone: $timezone,
            readyNowByDayPart: $this->readyNowByDayPart,
        );
    }

    /**
     * @return list<string>
     */
    public function cadenceDayPartSummary(): array
    {
        $parts = [];

        foreach ($this->cadenceByDayPart as $part => $count) {
            if ($count > 0) {
                $parts[] = $count.' '.$part;
            }
        }

        return $parts;
    }

    public function cadenceDayPartDescription(): string
    {
        return implode(' · ', $this->cadenceDayPartSummary());
    }

    /**
     * @return list<string>
     */
    public function readyNowDayPartSummary(): array
    {
        $present = [];

        foreach ($this->readyNowByDayPart as $part => $count) {
            if ($count > 0) {
                $present[$part] = $count;
            }
        }

        if ($present === []) {
            return [];
        }

        $mixed = count($present) > 1;

        $parts = [];

        foreach ($present as $part => $count) {
            $label = CadenceDefaults::label($part);
            $parts[] = $mixed ? $count.' '.$label : $label;
        }

        return $parts;
    }

    public function readyNowDayPartDescription(): ?string
    {
        $parts = $this->readyNowDayPartSummary();

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public function cadenceWaitSlotDescription(): string
    {
        $parts = [];

        foreach ($this->cadenceWaitSlots as $slot) {
            if ($slot['count'] > 0) {
                $parts[] = $slot['count'].' '.$slot['label'];
            }
        }

        return implode(' · ', $parts);
    }

    public function hasQueuePressure(): bool
    {
        return $this->readyNow === 0
            && ($this->waitingCadence > 0 || $this->maxAttempts > 0);
    }

    /**
     * @return list<array{label: string, count: int, indent: bool, timing: ?string}>
     */
    public function queueStatusRows(): array
    {
        $rows = [
            [
                'label' => 'Ready now',
                'count' => $this->readyNow,
                'indent' => false,
                'timing' => $this->readyNowDayPartDescription(),
            ],
        ];

        if ($this->waitingCadence > 0) {
            $rows[] = ['label' => 'Waiting on cadence', 'count' => $this->waitingCadence, 'indent' => false, 'timing' => null];

            foreach ($this->cadenceWaitSlots as $slot) {
                $rows[] = [
                    'label' => $slot['label'],
                    'count' => $slot['count'],
                    'indent' => true,
                    'timing' => null,
                ];
            }
        }

        $rows[] = ['label' => 'Waiting on legal hours', 'count' => $this->waitingHours, 'indent' => false, 'timing' => null];
        $rows[] = ['label' => 'On an active claim', 'count' => $this->claimed, 'indent' => false, 'timing' => null];
        $rows[] = ['label' => 'At max attempts', 'count' => $this->maxAttempts, 'indent' => false, 'timing' => null];
        $rows[] = ['label' => 'Callbacks due now', 'count' => $this->callbacksDue, 'indent' => false, 'timing' => null];
        $rows[] = ['label' => 'Callbacks scheduled', 'count' => $this->callbacksScheduled, 'indent' => false, 'timing' => null];

        return $rows;
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    public function cadenceWaitSlotRows(): array
    {
        return $this->cadenceWaitSlots;
    }

    public static function formatCadenceWindowLabel(
        string $date,
        string $dayPart,
        string $windowStart,
        string $timezone,
    ): string {
        $local = Carbon::parse($date.' '.$windowStart, $timezone);
        $now = now()->timezone($timezone);

        $dayLabel = match (true) {
            $date === $now->toDateString() => 'Today',
            $date === $now->copy()->addDay()->toDateString() => 'Tomorrow',
            default => $local->format('M j'),
        };

        $partLabel = $dayPart !== 'other'
            ? CadenceDefaults::label($dayPart).' · '
            : '';

        return $dayLabel.', '.$partLabel.$local->format('g:i A T');
    }

    public static function formatCadenceWaitSlotLabel(
        CarbonInterface $at,
        string $timezone,
        ?string $dayPart = null,
    ): string {
        $local = $at->copy()->timezone($timezone);
        $now = now()->timezone($timezone);

        if ($local->lte($now)) {
            return 'Now';
        }

        $dayLabel = match (true) {
            $local->isSameDay($now) => 'Today',
            $local->isSameDay($now->copy()->addDay()) => 'Tomorrow',
            default => $local->format('M j'),
        };

        $partLabel = $dayPart !== null && $dayPart !== 'other'
            ? CadenceDefaults::label($dayPart).' · '
            : '';

        return $dayLabel.', '.$partLabel.$local->format('g:i A T');
    }

    public static function formatEarliestDialable(?CarbonInterface $at, string $timezone): ?string
    {
        if ($at === null) {
            return null;
        }

        $local = $at->copy()->timezone($timezone);
        $now = now()->timezone($timezone);

        if ($local->lte($now)) {
            return 'Now';
        }

        return $local->format('M j, g:i A T');
    }
}
