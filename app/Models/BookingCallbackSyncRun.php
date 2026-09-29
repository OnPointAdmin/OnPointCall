<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingCallbackSyncRun extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'calling_list_id',
        'trigger',
        'started_at',
        'finished_at',
        'created_count',
        'updated_count',
        'skipped_no_phone_count',
        'skipped_dnc_terminal_count',
        'closed_count',
        'error_count',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function errors(): HasMany
    {
        return $this->hasMany(BookingCallbackSyncError::class);
    }

    public function callingList(): BelongsTo
    {
        return $this->belongsTo(CallingList::class);
    }

    public static function latestForCompany(int $companyId): ?self
    {
        return static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('finished_at')
            ->with('callingList')
            ->latest('id')
            ->first();
    }
}
