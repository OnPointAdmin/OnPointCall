<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasCheckBatchHealth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use BelongsToCompany;
    use HasCheckBatchHealth;

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
        'ignore_national_dnc',
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
            'ignore_national_dnc' => 'boolean',
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
}
