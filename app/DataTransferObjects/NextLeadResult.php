<?php

namespace App\DataTransferObjects;

use App\Enums\EmptyQueueReason;
use App\Models\Lead;

readonly class NextLeadResult
{
    public function __construct(
        public ?Lead $lead = null,
        public ?EmptyQueueReason $emptyReason = null,
    ) {}

    public function hasLead(): bool
    {
        return $this->lead !== null;
    }
}
