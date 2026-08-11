<?php

namespace App\Services\Dashboard;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Models\AppSetting;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use Carbon\Carbon;

class ManagerDashboardService
{
    /**
     * @return array{bookings: int, calls: int, skips: int, overdue_callbacks: int, orphaned_callbacks: int}
     */
    public function todayStats(int $companyId): array
    {
        $range = $this->todayRange($companyId);

        $history = LeadHistory::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('occurred_at', [$range['start'], $range['end']]);

        $bookings = (clone $history)
            ->where('event_type', LeadHistoryType::Disposition)
            ->where('payload->disposition', Disposition::Booked->value)
            ->count();

        $calls = (clone $history)
            ->where('event_type', LeadHistoryType::Disposition)
            ->count();

        $skips = (clone $history)
            ->where('event_type', LeadHistoryType::Skip)
            ->count();

        $overdueCallbacks = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Callback)
            ->where('callback_at', '<', now())
            ->count();

        $orphanedCallbacks = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Callback)
            ->where(function ($query): void {
                $query->whereNull('callback_owner_id')
                    ->orWhereHas('callbackOwner', fn ($owner) => $owner->where('active', false));
            })
            ->count();

        return [
            'bookings' => $bookings,
            'calls' => $calls,
            'skips' => $skips,
            'overdue_callbacks' => $overdueCallbacks,
            'orphaned_callbacks' => $orphanedCallbacks,
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function priorDayRange(int $companyId): array
    {
        $settings = AppSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        $timezone = $settings?->dashboard_email_timezone ?? 'America/New_York';
        $start = Carbon::now($timezone)->subDay()->startOfDay()->utc();
        $end = Carbon::now($timezone)->subDay()->endOfDay()->utc();

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function todayRange(int $companyId): array
    {
        $settings = AppSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        $timezone = $settings?->dashboard_email_timezone ?? 'America/New_York';
        $start = Carbon::now($timezone)->startOfDay()->utc();
        $end = Carbon::now($timezone)->endOfDay()->utc();

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array<int, array{user_id: int, name: string, bookings: int, calls: int, skips: int, callbacks_pending: int}>
     */
    public function perAgentStatsForRange(int $companyId, Carbon $start, Carbon $end): array
    {
        $history = LeadHistory::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('actor_id')
            ->get();

        $aggregated = [];

        foreach ($history as $row) {
            $actorId = (int) $row->actor_id;

            if (! isset($aggregated[$actorId])) {
                $aggregated[$actorId] = [
                    'user_id' => $actorId,
                    'bookings' => 0,
                    'calls' => 0,
                    'skips' => 0,
                ];
            }

            if ($row->event_type === LeadHistoryType::Skip) {
                $aggregated[$actorId]['skips']++;
            }

            if ($row->event_type === LeadHistoryType::Disposition) {
                $aggregated[$actorId]['calls']++;

                if (($row->payload['disposition'] ?? null) === Disposition::Booked->value) {
                    $aggregated[$actorId]['bookings']++;
                }
            }
        }

        $users = User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('id');

        $result = [];

        foreach ($aggregated as $actorId => $row) {
            $user = $users->get($actorId);

            $callbacksPending = Lead::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('status', LeadStatus::Callback)
                ->where('callback_owner_id', $actorId)
                ->count();

            $result[] = [
                'user_id' => $actorId,
                'name' => $user?->name ?? 'Unknown',
                'bookings' => $row['bookings'],
                'calls' => $row['calls'],
                'skips' => $row['skips'],
                'callbacks_pending' => $callbacksPending,
            ];
        }

        return $result;
    }
}
