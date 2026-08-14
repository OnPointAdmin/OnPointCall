<?php

namespace App\Enums;

enum QualificationStatus: string
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

    public function isAssignable(): bool
    {
        return match ($this) {
            self::Pending => false,
            self::Qualified, self::NotQualified, self::Error => true,
        };
    }
}
