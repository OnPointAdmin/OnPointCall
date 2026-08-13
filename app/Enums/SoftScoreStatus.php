<?php

namespace App\Enums;

enum SoftScoreStatus: string
{
    case Pending = 'pending';
    case Complete = 'complete';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Complete => 'Complete',
            self::Error => 'Error',
        };
    }

    public function isAssignable(): bool
    {
        return match ($this) {
            self::Pending => false,
            self::Complete, self::Error => true,
        };
    }
}
