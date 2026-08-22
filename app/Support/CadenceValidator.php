<?php

namespace App\Support;

use App\Models\CadenceDayPart;
use Illuminate\Validation\ValidationException;

class CadenceValidator
{
    /**
     * @param  iterable<int, CadenceDayPart|array<string, mixed>>  $dayParts
     */
    public static function validateDayParts(iterable $dayParts): void
    {
        $enabled = [];

        foreach ($dayParts as $part) {
            $dayPart = is_array($part) ? (string) $part['day_part'] : $part->day_part;
            $waitValue = is_array($part) ? ($part['wait_after_value'] ?? null) : $part->wait_after_value;
            $waitUnit = is_array($part) ? ($part['wait_after_unit'] ?? null) : $part->wait_after_unit;
            $hasWaitValue = $waitValue !== null && $waitValue !== '';
            $hasWaitUnit = filled($waitUnit);

            if ($hasWaitValue xor $hasWaitUnit) {
                throw ValidationException::withMessages([
                    'dayParts' => sprintf(
                        '%s wait after dial requires both a value and a unit.',
                        CadenceDefaults::label($dayPart),
                    ),
                ]);
            }

            if ($hasWaitValue && (int) $waitValue < 1) {
                throw ValidationException::withMessages([
                    'dayParts' => sprintf(
                        '%s wait after dial must be at least 1.',
                        CadenceDefaults::label($dayPart),
                    ),
                ]);
            }

            $enabledFlag = is_array($part) ? (bool) ($part['enabled'] ?? false) : (bool) $part->enabled;

            if (! $enabledFlag) {
                continue;
            }

            $dayPart = is_array($part) ? (string) $part['day_part'] : $part->day_part;
            $start = self::timeToMinutes(is_array($part) ? (string) $part['window_start'] : (string) $part->window_start);
            $end = self::timeToMinutes(is_array($part) ? (string) $part['window_end'] : (string) $part->window_end);

            if ($start >= $end) {
                throw ValidationException::withMessages([
                    'dayParts' => sprintf('%s start time must be before end time.', CadenceDefaults::label($dayPart)),
                ]);
            }

            $enabled[] = [
                'day_part' => $dayPart,
                'start' => $start,
                'end' => $end,
            ];
        }

        if ($enabled === []) {
            throw ValidationException::withMessages([
                'dayParts' => 'At least one day part must be enabled.',
            ]);
        }

        for ($i = 0; $i < count($enabled); $i++) {
            for ($j = $i + 1; $j < count($enabled); $j++) {
                if (self::rangesOverlap($enabled[$i]['start'], $enabled[$i]['end'], $enabled[$j]['start'], $enabled[$j]['end'])) {
                    throw ValidationException::withMessages([
                        'dayParts' => sprintf(
                            '%s and %s windows overlap.',
                            CadenceDefaults::label($enabled[$i]['day_part']),
                            CadenceDefaults::label($enabled[$j]['day_part']),
                        ),
                    ]);
                }
            }
        }
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $attemptGaps
     */
    public static function validateAttemptGaps(iterable $attemptGaps): void
    {
        $thresholds = [];

        foreach ($attemptGaps as $gap) {
            $afterAttempt = (int) ($gap['after_attempt'] ?? 0);
            $waitValue = (int) ($gap['wait_value'] ?? 0);

            if ($afterAttempt < 1) {
                throw ValidationException::withMessages([
                    'attemptGaps' => 'After attempt must be at least 1.',
                ]);
            }

            if ($waitValue < 1) {
                throw ValidationException::withMessages([
                    'attemptGaps' => 'Wait value must be at least 1.',
                ]);
            }

            if (in_array($afterAttempt, $thresholds, true)) {
                throw ValidationException::withMessages([
                    'attemptGaps' => 'Each after-attempt threshold must be unique.',
                ]);
            }

            $thresholds[] = $afterAttempt;
        }

        if ($thresholds === []) {
            throw ValidationException::withMessages([
                'attemptGaps' => 'At least one attempt wait rule is required.',
            ]);
        }
    }

    private static function timeToMinutes(string $time): int
    {
        $parts = explode(':', substr($time, 0, 5));

        return ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
    }

    private static function rangesOverlap(int $startA, int $endA, int $startB, int $endB): bool
    {
        return $startA < $endB && $startB < $endA;
    }
}
