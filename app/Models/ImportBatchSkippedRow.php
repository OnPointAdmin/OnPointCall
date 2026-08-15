<?php

namespace App\Models;

use App\Enums\ImportSkipReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatchSkippedRow extends Model
{
    protected $fillable = [
        'import_batch_id',
        'existing_lead_id',
        'reason',
        'matched_on',
        'phone',
        'first_name',
        'last_name',
        'external_lead_id',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ImportSkipReason::class,
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function existingLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'existing_lead_id');
    }
}
