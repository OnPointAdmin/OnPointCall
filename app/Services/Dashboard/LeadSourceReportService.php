<?php

namespace App\Services\Dashboard;

use App\Enums\DispositionReportGroup;
use App\Enums\LeadHistoryType;
use App\Enums\LeadSourceGroupBy;
use App\Models\DispositionDefinition;
use App\Models\LeadHistory;
use Carbon\Carbon;

class LeadSourceReportService
{
    public function __construct(
        private readonly ManagerDashboardService $dashboardService,
    ) {}

    /**
     * @return array{
     *     group_by: LeadSourceGroupBy,
     *     totals: array{total_leads_called: int, booked: int, booked_percent: ?float},
     *     rows: list<array{venue: ?string, event: ?string, total_leads_called: int, booked: int, booked_percent: ?float}>,
     * }
     */
    public function report(
        int $companyId,
        ?int $actorId,
        ?string $leadType,
        Carbon $start,
        Carbon $end,
        int|string|null $callingListId = null,
        LeadSourceGroupBy $groupBy = LeadSourceGroupBy::VenueAndEvent,
    ): array {
        $history = $this->dashboardService->historyQuery($companyId, $actorId, $leadType, $start, $end, $callingListId)
            ->with(['lead' => function ($leadQuery): void {
                $leadQuery->withoutGlobalScopes()->select('id', 'venue', 'event');
            }])
            ->get();

        $reportGroupMap = $this->reportGroupMap($companyId);
        $buckets = [];
        $totalsCalled = 0;
        $totalsBooked = 0;

        foreach ($history as $row) {
            if ($row->event_type !== LeadHistoryType::Disposition && $row->event_type !== LeadHistoryType::Skip) {
                continue;
            }

            $venue = $this->normalizeSourceValue($row->lead?->venue);
            $event = $this->normalizeSourceValue($row->lead?->event);
            $key = $this->bucketKey($groupBy, $venue, $event);

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'venue' => $groupBy->showsVenue() ? $venue : null,
                    'event' => $groupBy->showsEvent() ? $event : null,
                    'total_leads_called' => 0,
                    'booked' => 0,
                ];
            }

            $buckets[$key]['total_leads_called']++;
            $totalsCalled++;

            if ($row->event_type === LeadHistoryType::Disposition) {
                $slug = (string) ($row->payload['disposition'] ?? '');
                $group = $reportGroupMap[$slug] ?? DispositionReportGroup::Other->value;

                if ($group === DispositionReportGroup::Booked->value) {
                    $buckets[$key]['booked']++;
                    $totalsBooked++;
                }
            }
        }

        $rows = array_values($buckets);

        foreach ($rows as &$row) {
            $row['booked_percent'] = $row['total_leads_called'] > 0
                ? round(($row['booked'] / $row['total_leads_called']) * 100, 1)
                : null;
        }
        unset($row);

        usort($rows, fn (array $a, array $b): int => $this->compareRows($a, $b, $groupBy));

        return [
            'group_by' => $groupBy,
            'totals' => [
                'total_leads_called' => $totalsCalled,
                'booked' => $totalsBooked,
                'booked_percent' => $totalsCalled > 0
                    ? round(($totalsBooked / $totalsCalled) * 100, 1)
                    : null,
            ],
            'rows' => $rows,
        ];
    }

    public function formatPercent(?float $percent): string
    {
        return $percent === null ? '—' : number_format($percent, 1).'%';
    }

    private function bucketKey(LeadSourceGroupBy $groupBy, string $venue, string $event): string
    {
        return match ($groupBy) {
            LeadSourceGroupBy::Venue => $venue,
            LeadSourceGroupBy::Event => $event,
            LeadSourceGroupBy::VenueAndEvent => $venue.'|'.$event,
        };
    }

    /**
     * @param  array{venue: ?string, event: ?string, booked: int}  $a
     * @param  array{venue: ?string, event: ?string, booked: int}  $b
     */
    private function compareRows(array $a, array $b, LeadSourceGroupBy $groupBy): int
    {
        if ($a['booked'] !== $b['booked']) {
            return $b['booked'] <=> $a['booked'];
        }

        if ($groupBy->showsVenue()) {
            $venueCompare = strcasecmp((string) $a['venue'], (string) $b['venue']);

            if ($venueCompare !== 0) {
                return $venueCompare;
            }
        }

        if ($groupBy->showsEvent()) {
            return strcasecmp((string) $a['event'], (string) $b['event']);
        }

        return 0;
    }

    private function normalizeSourceValue(?string $value): string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : '(none)';
    }

    /**
     * @return array<string, string>
     */
    private function reportGroupMap(int $companyId): array
    {
        return DispositionDefinition::indexedForCompany($companyId)
            ->mapWithKeys(fn (DispositionDefinition $definition): array => [
                $definition->slug => $definition->report_group->value,
            ])
            ->all();
    }
}
