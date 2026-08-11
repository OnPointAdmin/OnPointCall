<?php

namespace App\Enums;

enum Disposition: string
{
    case Booked = 'booked';
    case Callback = 'callback';
    case NoAnswer = 'no_answer';
    case Voicemail = 'voicemail';
    case NotInterested = 'not_interested';
    case WrongNumber = 'wrong_number';
    case BadLead = 'bad_lead';
    case Dnc = 'dnc';
    case Skip = 'skip';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Booked',
            self::Callback => 'Callback',
            self::NoAnswer => 'No Answer',
            self::Voicemail => 'Voicemail',
            self::NotInterested => 'Not Interested',
            self::WrongNumber => 'Wrong Number',
            self::BadLead => 'Bad Lead',
            self::Dnc => 'DNC',
            self::Skip => 'Skip',
        };
    }

    public function incrementsAttempt(): bool
    {
        return $this !== self::Skip;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::NotInterested, self::WrongNumber, self::BadLead], true);
    }
}
