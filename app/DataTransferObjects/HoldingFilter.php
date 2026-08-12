<?php

namespace App\DataTransferObjects;

readonly class HoldingFilter
{
    public function __construct(
        public ?string $leadType = null,
        public ?string $state = null,
        public ?string $venue = null,
        public ?string $event = null,
        public ?int $importBatchId = null,
        public ?string $importedFrom = null,
        public ?string $importedTo = null,
        public ?string $zip = null,
        public ?string $partner = null,
        public ?string $fileName = null,
        public ?string $softScoreStatus = null,
        public ?string $softScoreCode = null,
        public ?string $ageRange = null,
        public ?string $annualIncome = null,
        public ?string $maritalStatus = null,
        public ?string $gender = null,
        public ?string $homeOwner = null,
        public ?string $tourLocation = null,
        public ?string $tourDate = null,
    ) {}
}
