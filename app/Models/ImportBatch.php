<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'source_filename',
        'source_storage_path',
        'column_map',
        'imported_at',
        'total_rows',
        'inserted_count',
        'updated_count',
        'duplicate_count',
        'conflict_count',
        'lead_type',
        'run_soft_score',
        'run_rnd_check',
        'run_qualification',
        'run_dnc_check',
        'soft_score_pending',
        'soft_score_qualified',
        'soft_score_not_qualified',
        'soft_score_error',
        'rnd_pending',
        'rnd_clear',
        'rnd_reassigned',
        'rnd_no_data',
        'rnd_error',
        'qualification_pending',
        'qualification_qualified',
        'qualification_not_qualified',
        'qualification_error',
        'dnc_pending',
        'dnc_clear',
        'dnc_hit',
        'dnc_invalid',
        'dnc_error',
        'status',
        'error_message',
    ];

    protected $appends = [
        'valid_leads',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'column_map' => 'array',
            'run_soft_score' => 'boolean',
            'run_rnd_check' => 'boolean',
            'run_qualification' => 'boolean',
            'run_dnc_check' => 'boolean',
            'status' => ImportBatchStatus::class,
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function skippedRows(): HasMany
    {
        return $this->hasMany(ImportBatchSkippedRow::class);
    }

    /**
     * Inserted leads that remain usable after excluding RND reassignments
     * and DNC.com hits / invalid numbers.
     * Dupes/conflicts are already excluded from inserted_count.
     */
    public function getValidLeadsAttribute(): int
    {
        return max(0, (int) $this->inserted_count
            - (int) $this->rnd_reassigned
            - (int) $this->dnc_hit
            - (int) $this->dnc_invalid);
    }

    /**
     * Overall batch health for list indicators: ok | pending | error.
     */
    public function healthStatus(): string
    {
        if ($this->status === ImportBatchStatus::Failed || filled($this->error_message)) {
            return 'error';
        }

        if (in_array($this->status, [
            ImportBatchStatus::Pending,
            ImportBatchStatus::Processing,
        ], true)) {
            return 'pending';
        }

        if (
            ($this->run_soft_score && (int) $this->soft_score_pending > 0)
            || ($this->run_rnd_check && (int) $this->rnd_pending > 0)
            || ($this->run_qualification && (int) $this->qualification_pending > 0)
            || ($this->run_dnc_check && (int) $this->dnc_pending > 0)
        ) {
            return 'pending';
        }

        if (
            (int) $this->soft_score_error > 0
            || (int) $this->rnd_error > 0
            || (int) $this->qualification_error > 0
            || (int) $this->dnc_error > 0
        ) {
            return 'error';
        }

        return 'ok';
    }

    public function healthLabel(): string
    {
        return match ($this->healthStatus()) {
            'pending' => 'In progress',
            'error' => 'Errors',
            default => 'Healthy',
        };
    }
}
