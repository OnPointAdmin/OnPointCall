<?php

namespace App\Enums;

enum EmptyQueueReason: string
{
    case NoneAvailable = 'none_available';
    case BlockedByHours = 'blocked_by_hours';
    case BlockedByCadence = 'blocked_by_cadence';

    public function message(): string
    {
        return match ($this) {
            self::NoneAvailable => 'No leads are available in your assigned lists.',
            self::BlockedByHours => 'Leads are waiting, but none are callable during legal calling hours right now.',
            self::BlockedByCadence => 'Leads are waiting, but cadence timing prevents calling them right now.',
        };
    }
}
