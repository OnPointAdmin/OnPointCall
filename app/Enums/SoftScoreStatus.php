<?php

namespace App\Enums;

enum SoftScoreStatus: string
{
    case Pending = 'pending';
    case Qualified = 'qualified';
    case NotQualified = 'not_qualified';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Qualified => 'Qualified',
            self::NotQualified => 'Not Qualified',
            self::Error => 'Error',
        };
    }
}
