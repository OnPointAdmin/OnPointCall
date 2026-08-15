<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Dnc\DncService;
use App\Support\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DncPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(
        public int $leadId,
    ) {}

    public function handle(DncService $dncService): void
    {
        $lead = Lead::withoutGlobalScopes()->find($this->leadId);

        if (! $lead) {
            return;
        }

        CompanyContext::set($lead->company_id);

        try {
            $dncService->pushInternalDnc($lead);
        } finally {
            CompanyContext::clear();
        }
    }
}
