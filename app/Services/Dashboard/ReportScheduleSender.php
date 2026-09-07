<?php

namespace App\Services\Dashboard;

use App\Mail\DashboardDigestMail;
use App\Models\Company;
use App\Models\ReportSchedule;
use App\Support\CompanyTimezone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReportScheduleSender
{
    public function __construct(
        private readonly ReportScheduleMailService $mailService,
    ) {}

    /**
     * @return array{sent: bool, skipped: bool, message: string, recipient_count: int}
     */
    public function send(ReportSchedule $schedule, bool $force = false, ?Carbon $now = null): array
    {
        $schedule->loadMissing('recipients');

        $recipients = $schedule->recipientEmails();

        if ($recipients === []) {
            return $this->result(false, true, 'No recipients.', 0);
        }

        if (! $force && ! $schedule->enabled) {
            return $this->result(false, true, 'Schedule is disabled.', count($recipients));
        }

        $timezone = CompanyTimezone::for($schedule->company_id);
        $now = ($now ?? Carbon::now($timezone))->copy()->timezone($timezone);
        $slot = $now->format('Y-m-d H:i');

        if (! $force) {
            $days = $schedule->normalizedDaysOfWeek();

            if ($days !== [] && ! in_array($now->isoWeekday(), $days, true)) {
                return $this->result(false, true, 'Not scheduled for this weekday.', count($recipients));
            }

            $times = $schedule->normalizedSendTimes();

            if ($times !== [] && ! in_array($now->format('H:i'), $times, true)) {
                return $this->result(false, true, 'Not scheduled for this time.', count($recipients));
            }

            if ($schedule->last_sent_slot === $slot) {
                return $this->result(false, true, 'Already sent for this minute.', count($recipients));
            }
        }

        $digest = $this->mailService->build($schedule, $now);
        $company = $schedule->company()->withoutGlobalScopes()->first()
            ?? Company::withoutGlobalScopes()->find($schedule->company_id);

        try {
            Mail::to($recipients[0])
                ->bcc(array_slice($recipients, 1))
                ->send(new DashboardDigestMail($digest['subject'], $digest['html']));

            $schedule->forceFill(['last_sent_slot' => $slot])->saveQuietly();

            Log::info('Report schedule sent', [
                'schedule_id' => $schedule->id,
                'company_id' => $schedule->company_id,
                'recipients' => count($recipients),
            ]);

            return $this->result(
                true,
                false,
                'Sent '.($company?->name ?? 'schedule').' to '.count($recipients).' recipient(s).',
                count($recipients),
            );
        } catch (\Throwable $e) {
            Log::error('Report schedule failed', [
                'schedule_id' => $schedule->id,
                'company_id' => $schedule->company_id,
                'error' => $e->getMessage(),
            ]);

            return $this->result(false, false, $e->getMessage(), count($recipients));
        }
    }

    /**
     * @return array{sent: bool, skipped: bool, message: string, recipient_count: int}
     */
    private function result(bool $sent, bool $skipped, string $message, int $recipientCount): array
    {
        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'message' => $message,
            'recipient_count' => $recipientCount,
        ];
    }
}
