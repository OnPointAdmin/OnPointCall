<?php

namespace Database\Seeders;

use App\Models\LeadTypeDefinition;
use Illuminate\Database\Seeder;

class LeadTypeSeeder extends Seeder
{
    public function run(int $companyId): void
    {
        $types = [
            [
                'slug' => 'standard',
                'name' => 'Standard',
                'active' => true,
            ],
            [
                'slug' => 'tnb',
                'name' => 'TNB',
                'active' => true,
            ],
        ];

        foreach ($types as $type) {
            LeadTypeDefinition::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'slug' => $type['slug'],
                ],
                $type + ['company_id' => $companyId],
            );
        }
    }
}
