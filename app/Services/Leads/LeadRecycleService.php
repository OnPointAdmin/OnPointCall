<?php

namespace App\Services\Leads;

use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeadRecycleService
{
    public function recycle(Lead $lead, User $actor): Lead
    {
        if ($lead->status === LeadStatus::Dnc) {
            throw new InvalidArgumentException('DNC leads cannot be recycled.');
        }

        return DB::transaction(function () use ($lead, $actor): Lead {
            $lead = Lead::withoutGlobalScopes()->lockForUpdate()->findOrFail($lead->id);

            $lead->update([
                'status' => LeadStatus::Callable,
                'attempt_count' => 0,
                'next_day_part' => null,
                'last_attempt_at' => null,
                'callback_owner_id' => null,
                'callback_at' => null,
            ]);

            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => $actor->id,
                'event_type' => LeadHistoryType::Recycle,
                'occurred_at' => now(),
                'payload' => [],
            ]);

            return $lead->fresh(['callingList']);
        });
    }
}
