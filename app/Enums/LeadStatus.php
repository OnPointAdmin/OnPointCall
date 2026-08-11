<?php

namespace App\Enums;

enum LeadStatus: string
{
    case Holding = 'holding';
    case Callable = 'callable';
    case Callback = 'callback';
    case Booked = 'booked';
    case Terminal = 'terminal';
    case Dnc = 'dnc';

    public function label(): string
    {
        return match ($this) {
            self::Holding => 'Holding',
            self::Callable => 'Callable',
            self::Callback => 'Callback',
            self::Booked => 'Booked',
            self::Terminal => 'Terminal',
            self::Dnc => 'DNC',
        };
    }
}
