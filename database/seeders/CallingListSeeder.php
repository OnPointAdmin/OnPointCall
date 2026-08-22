<?php

namespace Database\Seeders;

use App\Models\Cadence;
use App\Models\CallingList;
use Illuminate\Database\Seeder;

class CallingListSeeder extends Seeder
{
    public function run(int $companyId): void
    {
        $standardCadence = Cadence::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('name', 'Standard')
            ->firstOrFail();

        $lists = [
            [
                'name' => 'Standard',
                'lead_type' => 'standard',
                'cadence_id' => $standardCadence->id,
                'active' => true,
            ],
            [
                'name' => 'TNB',
                'lead_type' => 'tnb',
                'cadence_id' => $standardCadence->id,
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
