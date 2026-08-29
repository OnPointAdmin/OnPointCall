<?php

namespace App\Enums;

enum DispositionButtonGroup: string
{
    case Primary = 'primary';
    case Contact = 'contact';
    case Negative = 'negative';
    case Compliance = 'compliance';
    case Utility = 'utility';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary',
            self::Contact => 'Contact',
            self::Negative => 'Negative',
            self::Compliance => 'Compliance',
            self::Utility => 'Utility',
        };
    }
}
