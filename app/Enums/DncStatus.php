<?php

namespace App\Enums;

enum DncStatus: string
{
    case Pending = 'pending';
    case Clear = 'clear';
    case Hit = 'hit';
    case Invalid = 'invalid';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Clear => 'Clear',
            self::Hit => 'DNC',
            self::Invalid => 'Invalid',
            self::Error => 'Error',
        };
    }

    public function isAssignable(): bool
    {
        return $this === self::Clear;
    }
}
