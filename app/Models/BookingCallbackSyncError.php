<?php

namespace App\Models;

use App\Enums\BookingCallbackSyncErrorReason;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCallbackSyncError extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'booking_callback_sync_run_id',
        'booking_number',
        'salesforce_booking_id',
        'representative_name',
        'employee_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'reason' => BookingCallbackSyncErrorReason::class,
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(BookingCallbackSyncRun::class, 'booking_callback_sync_run_id');
    }
}
