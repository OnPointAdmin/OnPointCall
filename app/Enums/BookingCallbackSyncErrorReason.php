<?php

namespace App\Enums;

enum BookingCallbackSyncErrorReason: string
{
    case NoRepresentative = 'no_representative';
    case NoUser = 'no_user';
    case UserInactive = 'user_inactive';
    case NoCallbackDate = 'no_callback_date';

    public function label(): string
    {
        return match ($this) {
            self::NoRepresentative => 'No representative on booking',
            self::NoUser => 'No user with this Salesforce Id',
            self::UserInactive => 'User inactive',
            self::NoCallbackDate => 'No callback date',
        };
    }

    public function isAgentMatch(): bool
    {
        return $this !== self::NoCallbackDate;
    }
}
