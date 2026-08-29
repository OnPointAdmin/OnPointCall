<?php

namespace App\Enums;

enum DispositionReportGroup: string
{
    case Booked = 'booked';
    case NotInterested = 'not_interested';
    case NotQualified = 'not_qualified';
    case NoAnswerVm = 'no_answer_vm';
    case WrongDnc = 'wrong_dnc';
    case Callbacks = 'callbacks';
    case Skipped = 'skipped';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Booked',
            self::NotInterested => 'Not Interested',
            self::NotQualified => 'Not Qualified',
            self::NoAnswerVm => 'No Answer / VM',
            self::WrongDnc => 'Wrong / DNC',
            self::Callbacks => 'Callbacks',
            self::Skipped => 'Skipped',
            self::Other => 'Other',
        };
    }
}
