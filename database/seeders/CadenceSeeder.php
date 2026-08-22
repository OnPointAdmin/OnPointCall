<?php

namespace Database\Seeders;

use App\Models\Cadence;
use App\Support\CadenceDefaults;
use App\Support\CadenceProvisioner;
use Illuminate\Database\Seeder;

class CadenceSeeder extends Seeder
{
    public function run(int $companyId): void
    {
        $definitions = [
            [
                'name' => 'Standard',
                'dayParts' => CadenceDefaults::dayPartRows(),
                'attemptGaps' => CadenceDefaults::standardAttemptGaps(),
            ],
            [
                'name' => 'Aggressive',
                'dayParts' => CadenceDefaults::dayPartRows(['morning', 'evening']),
                'attemptGaps' => CadenceDefaults::aggressiveAttemptGaps(),
            ],
        ];

        foreach ($definitions as $definition) {
            $existing = Cadence::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('name', $definition['name'])
                ->first();

            if ($existing) {
                CadenceProvisioner::syncDayParts($existing, $definition['dayParts']);
                CadenceProvisioner::syncAttemptGaps($existing, $definition['attemptGaps']);

                continue;
            }

            CadenceProvisioner::create(
                companyId: $companyId,
                name: $definition['name'],
                dayParts: $definition['dayParts'],
                attemptGaps: $definition['attemptGaps'],
            );
        }
    }
}
