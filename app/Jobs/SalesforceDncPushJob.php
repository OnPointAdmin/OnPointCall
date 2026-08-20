<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Salesforce\SalesforceDncService;
use App\Support\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SalesforceDncPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(
        public int $leadId,
        public ?int $actorId = null,
    ) {}

    public function handle(SalesforceDncService $salesforceDnc): void
    {
        $lead = Lead::withoutGlobalScopes()->find($this->leadId);

        if (! $lead) {
            return;
        }

        CompanyContext::set($lead->company_id);

        try {
            $salesforceDnc->push($lead, $this->actorId);
        } finally {
            CompanyContext::clear();
        }
    }
}
