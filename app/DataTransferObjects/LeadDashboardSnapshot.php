<?php

namespace App\DataTransferObjects;

readonly class LeadDashboardSnapshot
{
    /**
     * @param  array<string, int>  $statusCounts
     * @param  list<array{key: string, label: string, count: int}>  $forecast
     * @param  list<array{name: string, total: int, holding: int, ready_now: int, waiting: int, callbacks: int}>  $byList
     */
    public function __construct(
        public int $total,
        public int $fresh,
        public array $statusCounts,
        public int $readyNow,
        public int $waiting,
        public int $claimed,
        public int $exhausted,
        public int $callbacksDue,
        public int $callbacksScheduled,
        public array $forecast,
        public array $byList,
        public string $timezone,
        public string $runAt,
    ) {}
}
