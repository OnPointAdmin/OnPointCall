<?php

namespace App\Enums;

enum LeadType: string
{
    case Standard = 'standard';
    case Tnb = 'tnb';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Tnb => 'TNB',
        };
    }
}
