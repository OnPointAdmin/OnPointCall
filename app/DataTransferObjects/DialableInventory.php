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
    ) {}

    public static function empty(): self
    {
        return new self(0, 0);
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
            ['label' => 'Ready now', 'count' => $this->readyNow, 'indent' => false, 'timing' => null],
        ];

        if ($this->waitingCadence > 0) {
            $rows[] = ['label' => 'Waiting on cadence', 'count' => $this->waitingCadence, 'indent' => false, 'timing' => null];

            foreach ($this->cadenceByDayPart as $part => $count) {
                if ($count > 0) {
                    $label = $part === 'other'
                        ? 'Other'
                        : CadenceDefaults::label($part);

                    $rows[] = [
                        'label' => $label,
                        'count' => $count,
                        'indent' => true,
                        'timing' => $this->cadenceEarliestByPart[$part] ?? null,
                    ];
                }
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
