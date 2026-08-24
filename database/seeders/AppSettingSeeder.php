<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(int $companyId): void
    {
        $settings = AppSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'max_attempts' => 6,
                'claim_ttl_minutes' => 20,
                'dashboard_email_enabled' => false,
                'dashboard_email_send_time' => '07:00:00',
                'dashboard_email_timezone' => 'America/New_York',
            ],
        );

        $settings->update([
            'booking_url_template' => 'https://peoplereally.win/data/i_opma_call.html',
            'booking_param_map' => ['2ff7-7114-0d49' => 'external_lead_id'],
        ]);
    }
}
