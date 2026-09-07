<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;

class LegacyDashboardEmailMigrator
{
    public function migrate(): int
    {
        $existingCompanyIds = DB::table('report_schedules')
            ->pluck('company_id')
            ->unique()
            ->all();

        $settings = DB::table('app_settings')->get()->keyBy('company_id');
        $recipientsByCompany = DB::table('dashboard_email_recipients')
            ->get()
            ->groupBy('company_id');

        $companyIds = $settings->keys()
            ->merge($recipientsByCompany->keys())
            ->unique()
            ->values();

        $created = 0;

        foreach ($companyIds as $companyId) {
            if (in_array($companyId, $existingCompanyIds, true)) {
                continue;
            }

            $row = $settings->get($companyId);
            $recipients = $recipientsByCompany->get($companyId, collect());
            $enabled = (bool) ($row->dashboard_email_enabled ?? false);

            if (! $enabled && $recipients->isEmpty()) {
                continue;
            }

            $rawTime = $row->dashboard_email_send_time ?? '07:00:00';
            $sendTime = '07:00';

            try {
                $sendTime = \Carbon\Carbon::parse((string) $rawTime)->format('H:i');
            } catch (\Throwable) {
                $sendTime = '07:00';
            }

            $scheduleId = DB::table('report_schedules')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Daily Agent Dashboard',
                'enabled' => $enabled,
                'report_type' => 'agent_dashboard',
                'period' => 'yesterday',
                'days_of_week' => json_encode([1, 2, 3, 4, 5, 6, 7]),
                'send_times' => json_encode([$sendTime]),
                'group_by' => null,
                'last_sent_slot' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($recipients as $recipient) {
                $email = strtolower(trim((string) $recipient->email));

                if ($email === '') {
                    continue;
                }

                DB::table('report_schedule_recipients')->insert([
                    'company_id' => $companyId,
                    'report_schedule_id' => $scheduleId,
                    'email' => $email,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $created++;
        }

        return $created;
    }
}
