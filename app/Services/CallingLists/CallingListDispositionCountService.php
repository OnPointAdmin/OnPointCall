<?php

namespace App\Services\CallingLists;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Models\CallingList;
use App\Models\Lead;
use App\Models\LeadHistory;

class CallingListDispositionCountService
{
    /**
     * Last disposition of leads currently on the list (same meaning as Leads "Last Disp").
     *
     * @return list<array{key: string, label: string, count: int, percent: ?float}>
     */
    public function forList(CallingList $list): array
    {
        $leadIds = Lead::query()
            ->where('calling_list_id', $list->id)
            ->pluck('id');

        $total = $leadIds->count();
        $counts = array_fill_keys(
            array_map(fn (Disposition $disposition): string => $disposition->value, Disposition::cases()),
            0,
        );
        $withDisposition = 0;

        if ($total > 0) {
            $latestIds = LeadHistory::query()
                ->where('event_type', LeadHistoryType::Disposition->value)
                ->whereIn('lead_id', $leadIds)
                ->selectRaw('MAX(id) as id')
                ->groupBy('lead_id')
                ->pluck('id');

            $payloads = LeadHistory::query()
                ->whereIn('id', $latestIds)
                ->pluck('payload');

            foreach ($payloads as $payload) {
                if (is_string($payload)) {
                    $payload = json_decode($payload, true);
                }

                $value = is_array($payload) ? ($payload['disposition'] ?? null) : null;

                if (! is_string($value) || $value === '' || ! array_key_exists($value, $counts)) {
                    continue;
                }

                $counts[$value]++;
                $withDisposition++;
            }
        }

        $none = $total - $withDisposition;
        $percent = fn (int $count): ?float => $total > 0
            ? round(100 * $count / $total, 1)
            : null;

        $items = [
            [
                'key' => 'total',
                'label' => 'Total',
                'count' => $total,
                'percent' => $total > 0 ? 100.0 : null,
            ],
        ];

        foreach (Disposition::cases() as $disposition) {
            $count = $counts[$disposition->value];

            $items[] = [
                'key' => $disposition->value,
                'label' => $disposition->label(),
                'count' => $count,
                'percent' => $percent($count),
            ];
        }

        $items[] = [
            'key' => 'none',
            'label' => 'None',
            'count' => $none,
            'percent' => $percent($none),
        ];

        return $items;
    }
}
