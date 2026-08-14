<?php

namespace App\Enums;

enum Disposition: string
{
    case Booked = 'booked';
    case Callback = 'callback';
    case NoAnswer = 'no_answer';
    case LeftVm = 'left_vm';
    case NotInterested = 'not_interested';
    case NotQualified = 'not_qualified';
    case Dnc = 'dnc';
    case BadNumber = 'bad_number';
    case WrongNumber = 'wrong_number';
    case Skip = 'skip';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Booked',
            self::Callback => 'Callback',
            self::NoAnswer => 'No Answer',
            self::LeftVm => 'Left VM',
            self::NotInterested => 'Not Interested',
            self::NotQualified => 'Not Qualified',
            self::Dnc => 'DNC',
            self::BadNumber => 'Bad Number',
            self::WrongNumber => 'Wrong Number',
            self::Skip => 'Skip',
        };
    }

    public function incrementsAttempt(): bool
    {
        return $this !== self::Skip;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::NotInterested,
            self::NotQualified,
            self::BadNumber,
            self::WrongNumber,
        ], true);
    }
}
