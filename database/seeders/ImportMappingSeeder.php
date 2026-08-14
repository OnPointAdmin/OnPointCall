<?php

namespace Database\Seeders;

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
                    'file_name' => 'File Name',
                ],
                'lead_type' => 'standard',
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
                    'age_range' => 'AgeRange',
                    'annual_income' => 'annual_income',
                    'marital_status' => 'Marital Status',
                    'gender' => 'Gender',
                    'home_owner' => 'HomeOwner',
                    'external_lead_id' => 'OP_Id',
                    'venue' => 'Venue',
                    'event' => 'Event',
                    'original_lead_submit_date' => 'original_lead_submit_date',
                    'soft_score_checked_at' => 'original_lead_submit_date',
                    'partner_list' => 'PartnerList',
                    'file_name' => 'File Name',
                ],
                'lead_type' => 'standard',
                'is_default' => true,
            ],
        );

        ImportMapping::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'name' => 'TNB',
            ],
            [
                'column_map' => [
                    'phone' => 'Phone_2',
                    'booking_id' => 'BookingId',
                    'phone_2' => 'Phone_2',
                    'first_name' => 'FirstName',
                    'last_name' => 'LastName',
                    'address_2' => 'Address2',
                    'tour_location' => 'TourLocation',
                    'tour_date_start' => 'TourDateStart',
                    'tour_date' => 'TourDate',
                    'premiums' => 'Premiums',
                    'tour_result' => 'Tour_Result',
                    'tour_or_no_show' => 'TourOrNoShow',
                ],
                'lead_type' => 'tnb',
                'is_default' => false,
            ],
        );
    }
}
