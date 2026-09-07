<?php

namespace App\Models;

use App\Enums\LeadSourceGroupBy;
use App\Enums\ReportSchedulePeriod;
use App\Enums\ReportScheduleType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use App\Support\Weekdays;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportSchedule extends Model
{
    use BelongsToCompany, RecordsSettingsChanges;

    protected $fillable = [
        'company_id',
        'name',
        'enabled',
        'report_type',
        'period',
        'days_of_week',
        'send_times',
        'group_by',
        'last_sent_slot',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'report_type' => ReportScheduleType::class,
            'period' => ReportSchedulePeriod::class,
            'days_of_week' => 'array',
            'send_times' => 'array',
            'group_by' => LeadSourceGroupBy::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $schedule): void {
            $schedule->days_of_week = Weekdays::normalize($schedule->days_of_week);
            $schedule->send_times = self::normalizeSendTimes($schedule->send_times);

            if ($schedule->report_type === ReportScheduleType::LeadDashboard) {
                $schedule->period = null;
                $schedule->group_by = null;
            }

            if ($schedule->report_type !== ReportScheduleType::LeadSource) {
                $schedule->group_by = null;
            }
        });
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ReportScheduleRecipient::class);
    }

    /**
     * @return list<string>
     */
    public function recipientEmails(): array
    {
        return $this->recipients
            ->pluck('email')
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function normalizedSendTimes(): array
    {
        return self::normalizeSendTimes($this->send_times);
    }

    /**
     * @return list<int>
     */
    public function normalizedDaysOfWeek(): array
    {
        return Weekdays::normalize($this->days_of_week);
    }

    /**
     * @param  mixed  $times
     * @return list<string>
     */
    public static function normalizeSendTimes(mixed $times): array
    {
        return collect(is_array($times) ? $times : [])
            ->map(function (mixed $time): ?string {
                $value = is_array($time) ? ($time['time'] ?? reset($time)) : $time;

                if (! is_string($value) && ! is_numeric($value)) {
                    return null;
                }

                try {
                    return Carbon::parse((string) $value)->format('H:i');
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
