<?php

namespace App\Support;

use App\Models\Cadence;
use App\Models\CadenceAttemptGap;
use App\Models\CadenceDayPart;

class CadenceProvisioner
{
    /**
     * @param  list<array{day_part: string, rotation_order: int, enabled: bool, window_start: string, window_end: string}>|null  $dayParts
     * @param  list<array{after_attempt: int, wait_value: int, wait_unit: string}>|null  $attemptGaps
     */
    public static function create(
        int $companyId,
        string $name,
        bool $prioritizeUnattempted = true,
        bool $active = true,
        ?array $dayParts = null,
        ?array $attemptGaps = null,
    ): Cadence {
        $cadence = Cadence::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $companyId,
                'name' => $name,
            ],
            [
                'prioritize_unattempted' => $prioritizeUnattempted,
                'active' => $active,
            ],
        );

        self::syncDayParts($cadence, $dayParts ?? CadenceDefaults::dayPartRows());
        self::syncAttemptGaps($cadence, $attemptGaps ?? CadenceDefaults::standardAttemptGaps());

        return $cadence->load(['dayParts', 'attemptGaps']);
    }

    public static function ensureChildren(Cadence $cadence): void
    {
        if ($cadence->dayParts()->count() === 0) {
            self::syncDayParts($cadence, CadenceDefaults::dayPartRows());
        }

        if ($cadence->attemptGaps()->count() === 0) {
            self::syncAttemptGaps($cadence, CadenceDefaults::standardAttemptGaps());
        }
    }

    /**
     * @param  list<array{day_part: string, rotation_order: int, enabled: bool, window_start: string, window_end: string}>  $rows
     */
    public static function syncDayParts(Cadence $cadence, array $rows): void
    {
        foreach ($rows as $row) {
            CadenceDayPart::withoutGlobalScopes()->updateOrCreate(
                [
                    'cadence_id' => $cadence->id,
                    'day_part' => $row['day_part'],
                ],
                [
                    'rotation_order' => $row['rotation_order'],
                    'enabled' => $row['enabled'],
                    'window_start' => self::normalizeTime($row['window_start']),
                    'window_end' => self::normalizeTime($row['window_end']),
                    'wait_after_value' => $row['wait_after_value'] ?? null,
                    'wait_after_unit' => $row['wait_after_unit'] ?? null,
                ],
            );
        }
    }

    /**
     * @param  list<array{after_attempt: int, wait_value: int, wait_unit: string}>  $rows
     */
    public static function syncAttemptGaps(Cadence $cadence, array $rows): void
    {
        foreach ($rows as $row) {
            CadenceAttemptGap::withoutGlobalScopes()->updateOrCreate(
                [
                    'cadence_id' => $cadence->id,
                    'after_attempt' => $row['after_attempt'],
                ],
                [
                    'wait_value' => $row['wait_value'],
                    'wait_unit' => $row['wait_unit'],
                ],
            );
        }

        $thresholds = array_column($rows, 'after_attempt');

        CadenceAttemptGap::withoutGlobalScopes()
            ->where('cadence_id', $cadence->id)
            ->when($thresholds !== [], fn ($query) => $query->whereNotIn('after_attempt', $thresholds))
            ->when($thresholds === [], fn ($query) => $query)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(Cadence $cadence): array
    {
        $cadence->loadMissing(['dayParts', 'attemptGaps']);

        return [
            'cadence' => $cadence->only([
                'id',
                'company_id',
                'name',
                'prioritize_unattempted',
                'active',
            ]),
            'day_parts' => $cadence->dayParts
                ->sortBy('rotation_order')
                ->values()
                ->map(fn (CadenceDayPart $part): array => $part->only([
                    'id',
                    'day_part',
                    'rotation_order',
                    'enabled',
                    'window_start',
                    'window_end',
                    'wait_after_value',
                    'wait_after_unit',
                ]))
                ->all(),
            'attempt_gaps' => $cadence->attemptGaps
                ->sortBy('after_attempt')
                ->values()
                ->map(fn (CadenceAttemptGap $gap): array => $gap->only([
                    'id',
                    'after_attempt',
                    'wait_value',
                    'wait_unit',
                ]))
                ->all(),
        ];
    }

    private static function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
