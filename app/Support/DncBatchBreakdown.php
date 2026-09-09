<?php

namespace App\Support;

readonly class DncBatchBreakdown
{
    public function __construct(
        public int $litigator = 0,
        public int $internal = 0,
        public int $national = 0,
        public int $state = 0,
        public int $dnc = 0,
        public int $nationalIgnored = 0,
        public int $stateIgnored = 0,
    ) {}

    public function blockingHits(): int
    {
        return $this->litigator + $this->internal + $this->national + $this->state + $this->dnc;
    }

    public function ignoredHits(): int
    {
        return $this->nationalIgnored + $this->stateIgnored;
    }
}
