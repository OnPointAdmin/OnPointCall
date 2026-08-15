<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Dnc\DncService;
use App\Support\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DncScrubJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;

    /**
     * @param  list<int>  $leadIds
     */
    public function __construct(
        public array $leadIds,
        public ?int $batchId = null,
        public ?int $actorId = null,
    ) {}

    /**
     * @param  list<int>  $leadIds
     */
    public static function dispatchForLeadIds(array $leadIds, ?int $batchId = null, ?int $actorId = null): int
    {
        $ids = array_values(array_unique(array_filter($leadIds)));

        if ($ids === []) {
            return 0;
        }

        $dispatched = 0;

        foreach (array_chunk($ids, 25) as $chunk) {
            self::dispatch($chunk, $batchId, $actorId);
            $dispatched++;
        }

        return $dispatched;
    }

    public function handle(DncService $dncService): void
    {
        $leads = Lead::withoutGlobalScopes()
            ->whereIn('id', $this->leadIds)
            ->get();

        if ($leads->isEmpty()) {
            return;
        }

        CompanyContext::set((int) $leads->first()->company_id);

        try {
            $dncService->checkLeads($leads, $this->actorId);
        } finally {
            CompanyContext::clear();
        }
    }
}
