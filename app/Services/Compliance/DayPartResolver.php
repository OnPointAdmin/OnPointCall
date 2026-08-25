<?php

namespace App\Services\Compliance;

use App\Models\CadenceDayPart;
use App\Models\Lead;
use App\Support\CadenceDefaults;
use App\Support\CadenceWait;
use Carbon\CarbonInterface;

class DayPartResolver
{
    /**
     * @return list<string>
     */
    public function dayPartsFor(Lead $lead): array
    {
        $parts = $this->enabledDayParts($lead);

        if ($parts !== []) {
            return $parts->pluck('day_part')->all();
        }

        return CadenceDefaults::DAY_PARTS;
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

    /**
     * Day-part windows this lead may be served in (rotation-aware).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function eligibleWindows(Lead $lead): array
    {
        $windows = $this->windowsFor($lead);
        $parts = $this->dayPartsFor($lead);

        if ($lead->next_day_part === null || ! in_array($lead->next_day_part, $parts, true)) {
            return $windows;
        }

        if (! isset($windows[$lead->next_day_part])) {
            return $windows;
        }

        return [$lead->next_day_part => $windows[$lead->next_day_part]];
    }

    public function waitAfterEligibleAt(Lead $lead): ?CarbonInterface
    {
        if ($lead->last_attempt_at === null) {
            return null;
        }

        $dialedInPart = $this->currentDayPart($lead, $lead->last_attempt_at);

        if ($dialedInPart === null) {
            return null;
        }

        $row = $this->dayPartRow($lead, $dialedInPart);

        if ($row === null || $row->wait_after_value === null || $row->wait_after_unit === null) {
            return null;
        }

        return CadenceWait::eligibleAt(
            $lead,
            $row->wait_after_value,
            $row->wait_after_unit,
            $lead->last_attempt_at,
        );
    }

    public function matchesNextDayPart(Lead $lead, ?CarbonInterface $at = null): bool
    {
        $at ??= now();
        $parts = $this->dayPartsFor($lead);

        if ($lead->next_day_part === null || ! in_array($lead->next_day_part, $parts, true)) {
            return in_array($this->currentDayPart($lead, $at), $parts, true);
        }

        if (! $this->isDialWaitSatisfied($lead, $at)) {
            return false;
        }

        return $lead->next_day_part === $this->currentDayPart($lead, $at);
    }

    public function advanceDayPart(Lead $lead, ?CarbonInterface $at = null): ?string
    {
        $parts = $this->dayPartsFor($lead);

        if ($parts === []) {
            return null;
        }

        $fromPart = $lead->next_day_part;

        if ($fromPart === null || ! in_array($fromPart, $parts, true)) {
            $fromPart = $this->currentDayPart($lead, $at);
        }

        if ($fromPart === null || ! in_array($fromPart, $parts, true)) {
            return $parts[0];
        }

        $currentIndex = array_search($fromPart, $parts, true);
        $nextIndex = ($currentIndex + 1) % count($parts);

        return $parts[$nextIndex];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function windowsFor(Lead $lead): array
    {
        $parts = $this->enabledDayParts($lead);

        if ($parts->isEmpty()) {
            return CadenceDefaults::windows();
        }

        $windows = [];

        foreach ($parts as $part) {
            $windows[$part->day_part] = [
                $this->formatTime($part->window_start),
                $this->formatTime($part->window_end),
            ];
        }

        return $windows;
    }

    /**
     * @return \Illuminate\Support\Collection<int, CadenceDayPart>
     */
    private function enabledDayParts(Lead $lead): \Illuminate\Support\Collection
    {
        $lead->loadMissing('callingList.cadence.dayParts');

        $dayParts = $lead->callingList?->cadence?->dayParts ?? collect();

        return $dayParts
            ->where('enabled', true)
            ->sortBy('rotation_order')
            ->values();
    }

    private function isDialWaitSatisfied(Lead $lead, CarbonInterface $at): bool
    {
        $eligibleAt = $this->waitAfterEligibleAt($lead);

        return $eligibleAt === null || $at->gte($eligibleAt);
    }

    private function dayPartRow(Lead $lead, string $dayPart): ?CadenceDayPart
    {
        $lead->loadMissing('callingList.cadence.dayParts');

        return $lead->callingList?->cadence?->dayParts
            ?->firstWhere('day_part', $dayPart);
    }

    private function formatTime(mixed $time): string
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
}
