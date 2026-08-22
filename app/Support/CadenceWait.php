<?php

namespace App\Support;

use App\Enums\CadenceWaitUnit;
use App\Models\Lead;
use Carbon\CarbonInterface;

class CadenceWait
{
    public static function eligibleAt(
        Lead $lead,
        int $waitValue,
        CadenceWaitUnit|string $waitUnit,
        CarbonInterface $from,
    ): CarbonInterface {
        $unit = $waitUnit instanceof CadenceWaitUnit
            ? $waitUnit
            : CadenceWaitUnit::from($waitUnit);

        return match ($unit) {
            CadenceWaitUnit::Minutes => $from->copy()->addMinutes($waitValue),
            CadenceWaitUnit::Hours => $from->copy()->addHours($waitValue),
            CadenceWaitUnit::Days => $from->copy()
                ->timezone($lead->timezone ?: 'America/New_York')
                ->startOfDay()
                ->addDays($waitValue)
                ->utc(),
        };
    }
}
