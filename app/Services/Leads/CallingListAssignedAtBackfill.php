<?php

namespace App\Services\Leads;

use App\Enums\LeadHistoryType;
use Illuminate\Support\Facades\DB;

class CallingListAssignedAtBackfill
{
    public function run(): int
    {
        $updated = 0;

        DB::table('leads')
            ->whereNotNull('calling_list_id')
            ->whereNull('calling_list_assigned_at')
            ->orderBy('id')
            ->chunkById(200, function ($leads) use (&$updated): void {
                $histories = DB::table('lead_history')
                    ->whereIn('lead_id', $leads->pluck('id'))
                    ->whereIn('event_type', [
                        LeadHistoryType::Release->value,
                        LeadHistoryType::Assign->value,
                    ])
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy('lead_id');

                foreach ($leads as $lead) {
                    $assignedAt = $this->assignedAtFromHistory(
                        $histories->get($lead->id, collect()),
                        (int) $lead->calling_list_id,
                    );

                    if ($assignedAt === null) {
                        continue;
                    }

                    DB::table('leads')->where('id', $lead->id)->update([
                        'calling_list_assigned_at' => $assignedAt,
                    ]);

                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $histories
     */
    private function assignedAtFromHistory($histories, int $callingListId): ?string
    {
        foreach ($histories as $history) {
            if ($this->historyTargetListId($history) === $callingListId) {
                return $history->occurred_at;
            }
        }

        return null;
    }

    private function historyTargetListId(object $history): ?int
    {
        $payload = is_string($history->payload)
            ? (json_decode($history->payload, true) ?? [])
            : (array) $history->payload;

        $raw = match ($history->event_type) {
            LeadHistoryType::Assign->value => $payload['to_calling_list_id'] ?? null,
            LeadHistoryType::Release->value => $payload['calling_list_id'] ?? null,
            default => null,
        };

        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }
}
