<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Rnd\RndService;
use App\Support\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RndLeadJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $leadId,
        public ?int $batchId = null,
        public ?int $actorId = null,
    ) {}

    public function handle(RndService $rndService): void
    {
        $lead = Lead::withoutGlobalScopes()->findOrFail($this->leadId);

        CompanyContext::set($lead->company_id);

        try {
            $rndService->checkLead($lead, $this->actorId);
        } finally {
            CompanyContext::clear();
        }
    }
}
