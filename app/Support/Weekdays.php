<?php

namespace App\Support;

final class Weekdays
{
    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];
    }

    /**
     * @param  list<int|string>|null  $days
     */
    public static function labels(?array $days): string
    {
        $options = self::options();
        $labels = collect($days ?? [])
            ->map(fn (int|string $day): ?string => $options[(int) $day] ?? null)
            ->filter()
            ->values();

        if ($labels->count() === 7) {
            return 'Every day';
        }

        return $labels->implode(', ') ?: '—';
    }

    /**
     * @param  mixed  $days
     * @return list<int>
     */
    public static function normalize(mixed $days): array
    {
        return collect(is_array($days) ? $days : [])
            ->map(fn (mixed $day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
