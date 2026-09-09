<?php

namespace App\Enums;

enum BookingCheckStatus: string
{
    case Pending = 'pending';
    case Clear = 'clear';
    case FutureHit = 'future_hit';
    case PastHit = 'past_hit';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Clear => 'Clear',
            self::FutureHit => 'Future booking',
            self::PastHit => 'Past booking',
            self::Error => 'Error',
        };
    }

    public function isAssignable(): bool
    {
        return $this === self::Clear;
    }
}
