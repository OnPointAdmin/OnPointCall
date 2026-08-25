<?php

namespace App\Enums;

enum ListAssignmentAction: string
{
    case Assigned = 'assigned';
    case Unassigned = 'unassigned';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Assigned',
            self::Unassigned => 'Unassigned',
        };
    }
}
