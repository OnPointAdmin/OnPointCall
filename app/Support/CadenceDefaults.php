<?php

namespace App\Support;

class CadenceDefaults
{
    /** @var list<string> */
    public const DAY_PARTS = ['morning', 'afternoon', 'evening'];

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function windows(): array
    {
        return [
            'morning' => ['08:00', '12:00'],
            'afternoon' => ['12:00', '17:00'],
            'evening' => ['17:00', '21:00'],
        ];
    }

    public static function label(string $dayPart): string
    {
        return match ($dayPart) {
            'morning' => 'Morning',
            'afternoon' => 'Afternoon',
            'evening' => 'Evening',
            default => ucfirst(str_replace('_', ' ', $dayPart)),
        };
    }

    /**
     * @return array{wait_after_value: int|null, wait_after_unit: string|null}
     */
    public static function defaultWaitAfterDialFor(string $dayPart): array
    {
        return self::defaultWaitAfterDial()[$dayPart] ?? [
            'wait_after_value' => null,
            'wait_after_unit' => null,
        ];
    }

    /**
     * @return array<string, array{wait_after_value: int|null, wait_after_unit: string|null}>
     */
    public static function defaultWaitAfterDial(): array
    {
        return [
            'morning' => ['wait_after_value' => 18, 'wait_after_unit' => 'hours'],
            'afternoon' => ['wait_after_value' => 18, 'wait_after_unit' => 'hours'],
            'evening' => ['wait_after_value' => 11, 'wait_after_unit' => 'hours'],
        ];
    }

    /**
     * @param  list<string>|null  $enabledParts
     * @return list<array{day_part: string, rotation_order: int, enabled: bool, window_start: string, window_end: string, wait_after_value: int|null, wait_after_unit: string|null}>
     */
    public static function dayPartRows(?array $enabledParts = null): array
    {
        $enabledParts ??= self::DAY_PARTS;
        $rows = [];

        foreach ($enabledParts as $index => $dayPart) {
            [$start, $end] = self::windows()[$dayPart];

            $rows[] = [
                'day_part' => $dayPart,
                'rotation_order' => $index + 1,
                'enabled' => true,
                'window_start' => $start,
                'window_end' => $end,
                ...self::defaultWaitAfterDialFor($dayPart),
            ];
        }

        foreach (self::DAY_PARTS as $dayPart) {
            if (in_array($dayPart, $enabledParts, true)) {
                continue;
            }

            [$start, $end] = self::windows()[$dayPart];

            $rows[] = [
                'day_part' => $dayPart,
                'rotation_order' => count($rows) + 1,
                'enabled' => false,
                'window_start' => $start,
                'window_end' => $end,
                ...self::defaultWaitAfterDialFor($dayPart),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{after_attempt: int, wait_value: int, wait_unit: string}>
     */
    public static function standardAttemptGaps(): array
    {
        return [
            ['after_attempt' => 3, 'wait_value' => 2, 'wait_unit' => 'days'],
            ['after_attempt' => 9, 'wait_value' => 2, 'wait_unit' => 'days'],
            ['after_attempt' => 12, 'wait_value' => 30, 'wait_unit' => 'days'],
        ];
    }

    /**
     * @return list<array{after_attempt: int, wait_value: int, wait_unit: string}>
     */
    public static function aggressiveAttemptGaps(): array
    {
        return [
            ['after_attempt' => 1, 'wait_value' => 30, 'wait_unit' => 'minutes'],
            ['after_attempt' => 3, 'wait_value' => 3, 'wait_unit' => 'days'],
        ];
    }
}
