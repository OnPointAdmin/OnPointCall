<?php

namespace App\Services\Compliance;

use App\Models\Lead;
use Carbon\CarbonInterface;

class DayPartResolver
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const DEFAULT_WINDOWS = [
        'morning' => ['08:00', '12:00'],
        'afternoon' => ['12:00', '17:00'],
        'evening' => ['17:00', '21:00'],
    ];

    /**
     * @return list<string>
     */
    public function dayPartsFor(Lead $lead): array
    {
        $cadence = $lead->callingList?->cadence ?? [];

        return $cadence['day_parts'] ?? ['morning', 'afternoon', 'evening'];
    }

    public function minGapMinutesFor(Lead $lead): int
    {
        $cadence = $lead->callingList?->cadence ?? [];

        return (int) ($cadence['min_gap_minutes'] ?? 60);
    }

    public function currentDayPart(Lead $lead, ?CarbonInterface $at = null): ?string
    {
        $at ??= now();
        $local = $at->copy()->timezone($lead->timezone ?: 'America/New_York');
        $time = $local->format('H:i');

        foreach ($this->windowsFor($lead) as $part => [$start, $end]) {
            if ($time >= $start && $time < $end) {
                return $part;
            }
        }

        return null;
    }

    public function matchesNextDayPart(Lead $lead, ?CarbonInterface $at = null): bool
    {
        if ($lead->next_day_part === null) {
            return true;
        }

        return $lead->next_day_part === $this->currentDayPart($lead, $at);
    }

    public function isCadenceGapSatisfied(Lead $lead, ?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if ($lead->last_attempt_at === null) {
            return true;
        }

        return $lead->last_attempt_at->copy()->addMinutes($this->minGapMinutesFor($lead))->lte($at);
    }

    public function advanceDayPart(Lead $lead): ?string
    {
        $parts = $this->dayPartsFor($lead);

        if ($parts === []) {
            return null;
        }

        $currentIndex = $lead->next_day_part
            ? array_search($lead->next_day_part, $parts, true)
            : false;

        if ($currentIndex === false) {
            return $parts[0];
        }

        $nextIndex = ($currentIndex + 1) % count($parts);

        return $parts[$nextIndex];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function windowsFor(Lead $lead): array
    {
        $cadence = $lead->callingList?->cadence ?? [];
        $custom = $cadence['day_part_hours'] ?? null;

        if (is_array($custom) && $custom !== []) {
            $windows = [];

            foreach ($custom as $part => $range) {
                if (is_array($range) && count($range) >= 2) {
                    $windows[$part] = [$range[0], $range[1]];
                }
            }

            if ($windows !== []) {
                return $windows;
            }
        }

        return self::DEFAULT_WINDOWS;
    }
}
