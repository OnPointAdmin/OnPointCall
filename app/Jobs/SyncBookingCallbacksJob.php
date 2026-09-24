<?php

namespace App\Jobs;

use App\Services\Leads\BookingCallbackSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncBookingCallbacksJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $companyId,
        public bool $dryRun = false,
        public string $trigger = 'command',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(BookingCallbackSyncService $sync): array
    {
        return $sync->sync($this->companyId, $this->dryRun, $this->trigger);
    }
}
