<?php

namespace App\Enums;

enum ImportSkipReason: string
{
    case Duplicate = 'duplicate';
    case Conflict = 'conflict';
    case InvalidPhone = 'invalid_phone';

    public function label(): string
    {
        return match ($this) {
            self::Duplicate => 'Duplicate',
            self::Conflict => 'Conflict',
            self::InvalidPhone => 'Invalid phone',
        };
    }
}
