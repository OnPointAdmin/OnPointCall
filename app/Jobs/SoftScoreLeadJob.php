<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\SoftScore\SoftScoreService;
use App\Support\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SoftScoreLeadJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $leadId,
        public ?int $batchId = null,
        public ?int $actorId = null,
        public bool $dispatchQualificationAfter = false,
        public bool $force = false,
    ) {}

    public function handle(SoftScoreService $softScoreService): void
    {
        $lead = Lead::withoutGlobalScopes()->findOrFail($this->leadId);

        CompanyContext::set($lead->company_id);

        try {
            $softScoreService->scoreLead($lead, $this->actorId, $this->force);
        } finally {
            CompanyContext::clear();
        }

        if (! $this->dispatchQualificationAfter) {
            return;
        }

        $lead->refresh();

        // Soft Score finished and soft_score_code is on the lead — now qualify.
        QualifyLeadJob::dispatch($this->leadId, $this->batchId, $this->actorId, $this->force);
    }
}
