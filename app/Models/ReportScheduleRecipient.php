<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportScheduleRecipient extends Model
{
    use BelongsToCompany, RecordsSettingsChanges;

    protected $fillable = [
        'company_id',
        'report_schedule_id',
        'email',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $recipient): void {
            $recipient->email = strtolower(trim((string) $recipient->email));
        });
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class, 'report_schedule_id');
    }
}
