<?php

namespace Database\Seeders;

use App\Models\StateRule;
use Illuminate\Database\Seeder;

class StateRuleSeeder extends Seeder
{
    /**
     * @var list<int>
     */
    private const ALL_WEEKDAYS = [0, 1, 2, 3, 4, 5, 6];

    public function run(int $companyId): void
    {
        $rules = [
            [
                'state_code' => 'DEFAULT',
                'window_start' => '08:00:00',
                'window_end' => '21:00:00',
                'permitted_weekdays' => self::ALL_WEEKDAYS,
                'manual_dial_only' => false,
            ],
            [
                'state_code' => 'FL',
                'window_start' => '08:00:00',
                'window_end' => '21:00:00',
                'permitted_weekdays' => self::ALL_WEEKDAYS,
                'manual_dial_only' => true,
            ],
            [
                'state_code' => 'NY',
                'window_start' => '08:00:00',
                'window_end' => '21:00:00',
                'permitted_weekdays' => self::ALL_WEEKDAYS,
                'manual_dial_only' => false,
            ],
            [
                'state_code' => 'NJ',
                'window_start' => '08:00:00',
                'window_end' => '21:00:00',
                'permitted_weekdays' => self::ALL_WEEKDAYS,
                'manual_dial_only' => false,
            ],
        ];

        foreach ($rules as $rule) {
            StateRule::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'state_code' => $rule['state_code'],
                ],
                $rule + ['company_id' => $companyId],
            );
        }
    }
}
