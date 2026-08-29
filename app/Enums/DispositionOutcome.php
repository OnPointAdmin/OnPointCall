<?php

namespace App\Enums;

enum DispositionOutcome: string
{
    case Callable = 'callable';
    case Terminal = 'terminal';
    case Booked = 'booked';
    case Callback = 'callback';
    case Dnc = 'dnc';
    case Skip = 'skip';

    public function label(): string
    {
        return match ($this) {
            self::Callable => 'Callable (stay on list)',
            self::Terminal => 'Terminal (off list)',
            self::Booked => 'Booked',
            self::Callback => 'Callback',
            self::Dnc => 'DNC',
            self::Skip => 'Skip',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function customOptions(): array
    {
        return collect([self::Callable, self::Terminal])
            ->mapWithKeys(fn (self $outcome): array => [$outcome->value => $outcome->label()])
            ->all();
    }
}
