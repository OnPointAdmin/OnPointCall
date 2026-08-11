<?php

namespace Database\Seeders;

use App\Enums\LeadType;
use App\Models\ImportMapping;
use Illuminate\Database\Seeder;

class ImportMappingSeeder extends Seeder
{
    public function run(int $companyId): void
    {
        ImportMapping::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'name' => 'Standard Default',
            ],
            [
                'column_map' => [
                    'phone' => 'Phone',
                    'first_name' => 'First Name',
                    'last_name' => 'Last Name',
                    'address' => 'Address',
                    'city' => 'City',
                    'state' => 'State',
                    'zip' => 'Zip',
                    'email' => 'Email',
                    'external_lead_id' => 'Lead ID',
                    'venue' => 'Venue',
                    'event' => 'Event',
                    'partner_list' => 'Partner List',
                ],
                'lead_type' => LeadType::Standard,
                'is_default' => true,
            ],
        );
    }
}
