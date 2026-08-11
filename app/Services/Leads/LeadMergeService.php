<?php

namespace App\Services\Leads;

use App\Enums\LeadHistoryType;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeadMergeService
{
    public function merge(Lead $survivor, Lead $duplicate, User $actor): Lead
    {
        if ($survivor->company_id !== $duplicate->company_id) {
            throw new InvalidArgumentException('Leads must belong to the same company.');
        }

        if ($survivor->id === $duplicate->id) {
            throw new InvalidArgumentException('Cannot merge a lead with itself.');
        }

        return DB::transaction(function () use ($survivor, $duplicate, $actor): Lead {
            $survivor = Lead::withoutGlobalScopes()->lockForUpdate()->findOrFail($survivor->id);
            $duplicate = Lead::withoutGlobalScopes()->lockForUpdate()->findOrFail($duplicate->id);

            LeadHistory::withoutGlobalScopes()
                ->where('lead_id', $duplicate->id)
                ->update(['lead_id' => $survivor->id]);

            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $survivor->company_id,
                'lead_id' => $survivor->id,
                'actor_id' => $actor->id,
                'event_type' => LeadHistoryType::Merge,
                'occurred_at' => now(),
                'payload' => [
                    'merged_lead_id' => $duplicate->id,
                    'merged_phone' => $duplicate->phone,
                    'merged_external_lead_id' => $duplicate->external_lead_id,
                ],
            ]);

            $duplicate->delete();

            return $survivor->fresh(['callingList']);
        });
    }
}
