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
                'is_default' => false,
            ],
        );

        ImportMapping::query()
            ->where('company_id', $companyId)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        ImportMapping::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'name' => 'SSIS',
            ],
            [
                'column_map' => [
                    'phone' => 'caller_id',
                    'first_name' => 'first_name',
                    'last_name' => 'last_name',
                    'address' => 'address',
                    'city' => 'city',
                    'state' => 'state',
                    'zip' => 'zip',
                    'email' => 'email',
                    'external_lead_id' => 'OP_Id',
                    'venue' => 'Venue',
                    'event' => 'Event',
                    'partner_list' => 'PartnerList',
                    'extra.age_range' => 'AgeRange',
                    'extra.annual_income' => 'annual_income',
                    'extra.marital_status' => 'Marital Status',
                    'extra.gender' => 'Gender',
                    'extra.home_owner' => 'HomeOwner',
                    'extra.original_lead_submit_date' => 'original_lead_submit_date',
                    'file_name' => 'File Name',
                ],
                'lead_type' => LeadType::Standard,
                'is_default' => true,
            ],
        );
    }
}
