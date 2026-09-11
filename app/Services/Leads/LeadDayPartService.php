<?php

namespace App\Services\Leads;

use App\Enums\LeadHistoryType;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Support\CadenceDefaults;
use InvalidArgumentException;

class LeadDayPartService
{
    public function setNextDayPart(Lead $lead, ?string $dayPart, User $actor): bool
    {
        $nextDayPart = CadenceDefaults::storedValue($dayPart);

        if ($dayPart !== null
            && $dayPart !== ''
            && $dayPart !== 'any'
            && $nextDayPart === null) {
            throw new InvalidArgumentException("Invalid next day part [{$dayPart}].");
        }

        $from = $lead->next_day_part;

        if ($from === $nextDayPart) {
            return false;
        }

        $lead->update(['next_day_part' => $nextDayPart]);

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'actor_id' => $actor->id,
            'event_type' => LeadHistoryType::FieldEdit,
            'occurred_at' => now(),
            'payload' => [
                'changes' => [
                    'next_day_part' => [
                        'from' => $from,
                        'to' => $nextDayPart,
                    ],
                ],
            ],
        ]);

        return true;
    }
}
