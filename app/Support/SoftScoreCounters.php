<?php

namespace App\Support;

use App\Enums\SoftScoreStatus;

class SoftScoreCounters
{
    public static function isNotQualifiedCode(?string $code): bool
    {
        $normalized = strtoupper(trim((string) $code));

        return $normalized === '' || $normalized === 'NQ';
    }

    public static function completedColumn(SoftScoreStatus $status, ?string $code): ?string
    {
        return match ($status) {
            SoftScoreStatus::Pending => null,
            SoftScoreStatus::Error => 'soft_score_error',
            SoftScoreStatus::Complete, SoftScoreStatus::Recent => self::isNotQualifiedCode($code)
                ? 'soft_score_not_qualified'
                : 'soft_score_qualified',
        };
    }
}
