<?php

namespace App\Services\CallingLists;

use App\Enums\LeadHistoryType;
use App\Models\CallingList;
use App\Models\DispositionDefinition;
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
        $definitions = DispositionDefinition::indexedForCompany($list->company_id);
        $counts = $definitions->mapWithKeys(fn (DispositionDefinition $definition): array => [
            $definition->slug => 0,
        ])->all();
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

                if (! is_string($value) || $value === '') {
                    continue;
                }

                if (! array_key_exists($value, $counts)) {
                    $counts[$value] = 0;
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

        foreach ($definitions as $definition) {
            $count = $counts[$definition->slug] ?? 0;

            $items[] = [
                'key' => $definition->slug,
                'label' => $definition->label,
                'count' => $count,
                'percent' => $percent($count),
            ];
        }

        foreach ($counts as $slug => $count) {
            if ($definitions->has($slug)) {
                continue;
            }

            $items[] = [
                'key' => $slug,
                'label' => $slug,
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
