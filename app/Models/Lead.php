<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Enums\SoftScoreStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'phone',
        'phone_2',
        'first_name',
        'last_name',
        'address',
        'address_2',
        'city',
        'state',
        'zip',
        'email',
        'age_range',
        'annual_income',
        'marital_status',
        'gender',
        'home_owner',
        'original_lead_submit_date',
        'venue',
        'event',
        'tour_location',
        'tour_date',
        'premiums',
        'tour_result',
        'tour_or_no_show',
        'external_lead_id',
        'booking_id',
        'consent_token',
        'timezone',
        'status',
        'attempt_count',
        'next_day_part',
        'last_attempt_at',
        'callback_at',
        'callback_owner_id',
        'calling_list_id',
        'imported_at',
        'import_batch_id',
        'partner_list',
        'file_name',
        'queue_rank',
        'lead_type',
        'extra_fields',
        'soft_score_code',
        'soft_score_status',
        'soft_score_checked_at',
        'soft_score_last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'last_attempt_at' => 'datetime',
            'callback_at' => 'datetime',
            'imported_at' => 'datetime',
            'extra_fields' => 'array',
            'soft_score_status' => SoftScoreStatus::class,
            'soft_score_checked_at' => 'datetime',
        ];
    }

    public function callingList(): BelongsTo
    {
        return $this->belongsTo(CallingList::class);
    }

    public function callbackOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callback_owner_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function claim(): HasOne
    {
        return $this->hasOne(LeadClaim::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(LeadHistory::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
