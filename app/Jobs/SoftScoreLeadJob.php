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
    ) {}

    public function handle(SoftScoreService $softScoreService): void
    {
        $lead = Lead::withoutGlobalScopes()->findOrFail($this->leadId);

        CompanyContext::set($lead->company_id);

        try {
            $softScoreService->scoreLead($lead, $this->actorId);
        } finally {
            CompanyContext::clear();
        }
    }
}
