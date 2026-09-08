<?php

namespace App\Enums;

enum QualifiedPartnersMatch: string
{
    case InList = 'in_list';
    case Only = 'only';

    public function label(): string
    {
        return match ($this) {
            self::InList => 'In the list',
            self::Only => 'Just this partner',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
