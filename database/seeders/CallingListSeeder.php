<?php

namespace Database\Seeders;

use App\Models\CallingList;
use Illuminate\Database\Seeder;

class CallingListSeeder extends Seeder
{
    public function run(int $companyId): void
    {
        $lists = [
            [
                'name' => 'Standard',
                'lead_type' => 'standard',
                'cadence' => [
                    'day_parts' => ['morning', 'afternoon', 'evening'],
                    'min_gap_minutes' => 60,
                ],
                'active' => true,
            ],
            [
                'name' => 'TNB',
                'lead_type' => 'tnb',
                'cadence' => [
                    'day_parts' => ['morning', 'afternoon', 'evening'],
                    'min_gap_minutes' => 60,
                ],
                'active' => true,
                'booking_param_map' => [],
            ],
        ];

        foreach ($lists as $list) {
            CallingList::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'name' => $list['name'],
                ],
                $list + ['company_id' => $companyId],
            );
        }
    }
}
