<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportSchedulePeriod: string implements HasLabel
{
    case TodaySoFar = 'today_so_far';
    case Yesterday = 'yesterday';
    case ThisWeek = 'this_week';
    case LastWeek = 'last_week';

    public function getLabel(): string
    {
        return match ($this) {
            self::TodaySoFar => 'Today so far',
            self::Yesterday => 'Yesterday',
            self::ThisWeek => 'This Week',
            self::LastWeek => 'Last Week',
        };
    }

    public function presetKey(): ?string
    {
        return match ($this) {
            self::TodaySoFar => null,
            self::Yesterday => 'yesterday',
            self::ThisWeek => 'this_week',
            self::LastWeek => 'last_week',
        };
    }
}
