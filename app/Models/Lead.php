<?php

namespace App\Models;

use App\Enums\DncStatus;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
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
        'first_name_2',
        'last_name_2',
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
        'tour_date_start',
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
        'last_skipped_by_user_id',
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
        'rnd_status',
        'rnd_checked_at',
        'rnd_last_error',
        'qualification_status',
        'qualification_checked_at',
        'qualification_last_error',
        'qualification_result',
        'dnc_status',
        'dnc_checked_at',
        'dnc_last_error',
        'dnc_result',
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
            'rnd_status' => RndStatus::class,
            'rnd_checked_at' => 'datetime',
            'qualification_status' => QualificationStatus::class,
            'qualification_checked_at' => 'datetime',
            'qualification_result' => 'array',
            'dnc_status' => DncStatus::class,
            'dnc_checked_at' => 'datetime',
            'dnc_result' => 'array',
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

    public function lastSkippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_skipped_by_user_id');
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

    /**
     * @return HasOne<LeadHistory, $this>
     */
    public function latestDisposition(): HasOne
    {
        return $this->hasOne(LeadHistory::class)->ofMany(
            ['occurred_at' => 'max', 'id' => 'max'],
            function (Builder $query): void {
                $query->where('event_type', LeadHistoryType::Disposition->value);
            },
        );
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function leadTypeName(): string
    {
        $slug = trim((string) $this->lead_type);

        if ($slug === '') {
            return '—';
        }

        $name = LeadTypeDefinition::withoutGlobalScopes()
            ->where('company_id', $this->company_id)
            ->where('slug', $slug)
            ->value('name');

        return is_string($name) && $name !== '' ? $name : $slug;
    }

    /**
     * @return list<string>
     */
    public function qualifiedPartnerNames(): array
    {
        $result = $this->qualificationResponse();
        $names = [];

        foreach (['qualifiedCompaniesLead', 'qualifiedCompaniesBooking'] as $key) {
            foreach ($result[$key] ?? [] as $company) {
                $name = is_array($company) ? ($company['companyName'] ?? null) : null;

                if (is_string($name) && trim($name) !== '') {
                    $names[] = trim($name);
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function qualificationRequest(): ?array
    {
        $request = $this->qualification_result['request'] ?? null;

        return is_array($request) ? $request : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function qualificationResponse(): array
    {
        $result = $this->qualification_result ?? [];

        if (array_key_exists('request', $result) || array_key_exists('response', $result)) {
            $response = $result['response'] ?? null;

            return is_array($response) ? $response : [];
        }

        return $result;
    }

    /**
     * @return list<array{name: string, vertical: ?string, priority: ?string, combination: ?string}>
     */
    public function qualificationCompanies(string $listKey): array
    {
        $result = $this->qualificationResponse();
        $companies = [];

        foreach ($result[$listKey] ?? [] as $company) {
            if (! is_array($company)) {
                continue;
            }

            $name = trim((string) ($company['companyName'] ?? ''));

            if ($name === '') {
                continue;
            }

            $companies[] = [
                'name' => $name,
                'vertical' => self::nullableTrimmedString($company['vertical'] ?? null),
                'priority' => self::nullableTrimmedString($company['priority'] ?? null),
                'combination' => self::nullableTrimmedString($company['qualificationCombination'] ?? null),
            ];
        }

        return $companies;
    }

    /**
     * @return list<array{name: string, combination: ?string, failed: list<string>}>
     */
    public function qualificationFailedCriteria(): array
    {
        $failed = $this->qualificationResponse()['failedCriteria'] ?? [];

        if (! is_array($failed)) {
            return [];
        }

        $rows = [];

        foreach ($failed as $companyName => $details) {
            $combination = null;
            $criteria = [];

            if (is_array($details)) {
                $combination = self::nullableTrimmedString($details['combinationName'] ?? null);
                $list = $details['failedCriteria'] ?? [];

                if (is_array($list)) {
                    foreach ($list as $item) {
                        if (is_string($item) && trim($item) !== '') {
                            $criteria[] = trim($item);
                        }
                    }
                }
            }

            $rows[] = [
                'name' => is_string($companyName) ? $companyName : (string) $companyName,
                'combination' => $combination,
                'failed' => $criteria,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function dncPhones(): array
    {
        $phones = $this->dnc_result['phones'] ?? [];

        return is_array($phones) ? $phones : [];
    }

    private static function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
