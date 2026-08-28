<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Support\BookingParamMap;
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
            'booking_url_template' => BookingParamMap::FORM_URL,
            'booking_param_map' => BookingParamMap::all(),
        ]);
    }
}
