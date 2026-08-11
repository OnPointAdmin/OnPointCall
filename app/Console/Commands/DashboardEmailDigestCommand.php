<?php

namespace App\Console\Commands;

use App\Mail\DashboardDigestMail;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\DashboardEmailRecipient;
use App\Services\Dashboard\DashboardDigestService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DashboardEmailDigestCommand extends Command
{
    protected $signature = 'dashboard:email-digest {--company= : Company ID to send for} {--force : Skip send-time check}';

    protected $description = 'Send prior-day dashboard summary email to configured recipients';

    public function handle(DashboardDigestService $digestService): int
    {
        $companyQuery = Company::query();

        if ($this->option('company')) {
            $companyQuery->whereKey($this->option('company'));
        }

        foreach ($companyQuery->get() as $company) {
            $this->sendForCompany($company, $digestService);
        }

        return self::SUCCESS;
    }

    private function sendForCompany(Company $company, DashboardDigestService $digestService): void
    {
        $settings = AppSetting::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        if (! $settings?->dashboard_email_enabled) {
            return;
        }

        $recipients = DashboardEmailRecipient::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->pluck('email')
            ->filter()
            ->all();

        if ($recipients === []) {
            return;
        }

        $timezone = $settings->dashboard_email_timezone ?? 'America/New_York';
        $now = Carbon::now($timezone);

        if (! $this->option('force')) {
            $sendTime = Carbon::parse($settings->dashboard_email_send_time ?? '07:00:00', $timezone)
                ->setDate($now->year, $now->month, $now->day);

            if ($now->format('H:i') !== $sendTime->format('H:i')) {
                return;
            }
        }

        $day = $now->copy()->subDay();
        $digest = $digestService->buildForCompany($company, $day);

        try {
            $mail = new DashboardDigestMail($digest['subject'], $digest['html']);

            Mail::to($recipients[0])
                ->bcc(array_slice($recipients, 1))
                ->send($mail);

            $this->info("Digest sent for {$company->name} to ".count($recipients).' recipient(s).');
        } catch (\Throwable $e) {
            Log::error('Dashboard digest failed', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            $this->error("Digest failed for {$company->name}: {$e->getMessage()}");
        }
    }
}
