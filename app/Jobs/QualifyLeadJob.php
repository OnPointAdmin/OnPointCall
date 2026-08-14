<?php

namespace App\Jobs;

use App\Enums\SoftScoreStatus;
use App\Models\Lead;
use App\Services\Qualification\QualificationService;
use App\Support\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class QualifyLeadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 12;

    public function __construct(
        public int $leadId,
        public ?int $batchId = null,
        public ?int $actorId = null,
    ) {}

    public function handle(QualificationService $qualificationService): void
    {
        $lead = Lead::withoutGlobalScopes()->findOrFail($this->leadId);

        // Soft Score must finish (and persist soft_score_code) before qualification
        // when both were selected on import or Soft Score is still in flight.
        if ($lead->soft_score_status === SoftScoreStatus::Pending && $this->attempts() < 10) {
            $this->release(15);

            return;
        }

        CompanyContext::set($lead->company_id);

        try {
            $lead->refresh();
            $qualificationService->qualifyLead($lead, $this->actorId);
        } finally {
            CompanyContext::clear();
        }
    }
}
