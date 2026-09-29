<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\CallingList;
use App\Support\Weekdays;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCallbackSchedule extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'calling_list_id',
        'enabled',
        'days_of_week',
        'run_times',
        'last_run_slot',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'days_of_week' => 'array',
            'run_times' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $schedule): void {
            $schedule->days_of_week = Weekdays::normalize($schedule->days_of_week);
            $schedule->run_times = ReportSchedule::normalizeSendTimes($schedule->run_times);
        });
    }

    /**
     * @return list<int>
     */
    public function normalizedDaysOfWeek(): array
    {
        return Weekdays::normalize($this->days_of_week);
    }

    /**
     * @return list<string>
     */
    public function normalizedRunTimes(): array
    {
        return ReportSchedule::normalizeSendTimes($this->run_times);
    }

    public function callingList(): BelongsTo
    {
        return $this->belongsTo(CallingList::class);
    }
}
