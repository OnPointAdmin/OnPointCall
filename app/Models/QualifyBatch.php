<?php

namespace App\Models;

use App\Enums\QualifyBatchStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasCheckBatchHealth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class QualifyBatch extends Model
{
    use BelongsToCompany;
    use HasCheckBatchHealth;

    protected $fillable = [
        'company_id',
        'user_id',
        'lead_count',
        'filter',
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

    protected function casts(): array
    {
        return [
            'filter' => 'array',
            'run_soft_score' => 'boolean',
            'run_rnd_check' => 'boolean',
            'run_qualification' => 'boolean',
            'run_dnc_check' => 'boolean',
            'status' => QualifyBatchStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'qualify_batch_leads')
            ->withTimestamps();
    }

    public function hasPendingChecks(): bool
    {
        return ($this->run_soft_score && (int) $this->soft_score_pending > 0)
            || ($this->run_rnd_check && (int) $this->rnd_pending > 0)
            || ($this->run_qualification && (int) $this->qualification_pending > 0)
            || ($this->run_dnc_check && (int) $this->dnc_pending > 0);
    }

    public function syncStatusFromCounters(): void
    {
        if ($this->status === QualifyBatchStatus::Failed || filled($this->error_message)) {
            return;
        }

        if ($this->hasPendingChecks()) {
            if ($this->status !== QualifyBatchStatus::Processing) {
                $this->update(['status' => QualifyBatchStatus::Processing]);
            }

            return;
        }

        if (in_array($this->status, [
            QualifyBatchStatus::Pending,
            QualifyBatchStatus::Processing,
        ], true)) {
            $this->update(['status' => QualifyBatchStatus::Completed]);
        }
    }
}
