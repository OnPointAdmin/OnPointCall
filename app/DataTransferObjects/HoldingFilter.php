<?php

namespace App\DataTransferObjects;

use App\Enums\LeadType;

readonly class HoldingFilter
{
    public function __construct(
        public ?LeadType $leadType = null,
        public ?string $state = null,
        public ?string $venue = null,
        public ?string $event = null,
        public ?int $importBatchId = null,
        public ?string $importedFrom = null,
        public ?string $importedTo = null,
        public ?string $zip = null,
        public ?string $partner = null,
        public ?string $softScoreStatus = null,
        public ?string $softScoreCode = null,
    ) {}
}
