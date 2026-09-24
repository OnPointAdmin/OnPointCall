<?php

namespace App\Services\Leads;

use App\Jobs\SyncBookingCallbacksJob;
use App\Models\BookingCallbackSchedule;
use App\Support\CompanyTimezone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;

class BookingCallbackScheduleRunner
{
    /**
     * @return array{ran: int, skipped: int}
     */
    public function run(?Carbon $now = null): array
    {
        $ran = 0;
        $skipped = 0;

        $schedules = BookingCallbackSchedule::withoutGlobalScopes()
            ->where('enabled', true)
            ->orderBy('id')
            ->get();

        foreach ($schedules as $schedule) {
            if (! $this->isDue($schedule, $now)) {
                $skipped++;

                continue;
            }

            $timezone = CompanyTimezone::for($schedule->company_id);
            $localNow = ($now ?? Carbon::now($timezone))->copy()->timezone($timezone);
            $slot = $localNow->format('Y-m-d H:i');

            $result = Bus::dispatchNow(new SyncBookingCallbacksJob(
                $schedule->company_id,
                false,
                'schedule',
            ));

            if (! empty($result['refused']) || ! empty($result['failed'])) {
                $skipped++;

                continue;
            }

            $schedule->forceFill(['last_run_slot' => $slot])->saveQuietly();
            $ran++;
        }

        return ['ran' => $ran, 'skipped' => $skipped];
    }

    public function isDue(BookingCallbackSchedule $schedule, ?Carbon $now = null): bool
    {
        if (! $schedule->enabled) {
            return false;
        }

        $timezone = CompanyTimezone::for($schedule->company_id);
        $localNow = ($now ?? Carbon::now($timezone))->copy()->timezone($timezone);
        $days = $schedule->normalizedDaysOfWeek();

        if ($days !== [] && ! in_array($localNow->isoWeekday(), $days, true)) {
            return false;
        }

        $times = $schedule->normalizedRunTimes();

        if ($times === [] || ! in_array($localNow->format('H:i'), $times, true)) {
            return false;
        }

        return $schedule->last_run_slot !== $localNow->format('Y-m-d H:i');
    }
}
