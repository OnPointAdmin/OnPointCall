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
        'imported_at',
        'total_rows',
        'inserted_count',
        'duplicate_count',
        'conflict_count',
        'lead_type',
        'run_soft_score',
        'soft_score_pending',
        'soft_score_qualified',
        'soft_score_not_qualified',
        'soft_score_error',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'run_soft_score' => 'boolean',
            'status' => ImportBatchStatus::class,
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
