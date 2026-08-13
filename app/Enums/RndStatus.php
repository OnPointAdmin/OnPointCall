<?php

namespace App\Enums;

enum RndStatus: string
{
    case Pending = 'pending';
    case Clear = 'clear';
    case Reassigned = 'reassigned';
    case NoData = 'no_data';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Clear => 'Clear',
            self::Reassigned => 'Reassigned',
            self::NoData => 'No Data',
            self::Error => 'Error',
        };
    }

    public function isAssignable(): bool
    {
        return match ($this) {
            self::Clear, self::NoData => true,
            default => false,
        };
    }
}
