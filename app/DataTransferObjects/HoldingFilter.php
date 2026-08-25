<?php

namespace App\DataTransferObjects;

readonly class HoldingFilter
{
    /**
     * @param  list<string>|null  $state
     * @param  list<string>|null  $venue
     * @param  list<string>|null  $event
     * @param  list<string>|null  $partner
     * @param  list<string>|null  $softScoreCode
     * @param  list<string>|null  $ageRange
     * @param  list<string>|null  $annualIncome
     * @param  list<string>|null  $maritalStatus
     * @param  list<string>|null  $gender
     * @param  list<string>|null  $homeOwner
     * @param  list<string>|null  $tourLocation
     * @param  list<string>|null  $tourDateStart
     * @param  list<string>|null  $tourDate
     * @param  list<string>|null  $tourResult
     * @param  list<string>|null  $lastDispositions
     */
    public function __construct(
        public ?string $leadType = null,
        public ?int $sourceCallingListId = null,
        public ?array $state = null,
        public ?array $venue = null,
        public ?array $event = null,
        public ?int $importBatchId = null,
        public ?string $importedFrom = null,
        public ?string $importedTo = null,
        public ?string $zip = null,
        public ?array $partner = null,
        public ?string $fileName = null,
        public ?array $softScoreCode = null,
        public ?array $ageRange = null,
        public ?array $annualIncome = null,
        public ?array $maritalStatus = null,
        public ?array $gender = null,
        public ?array $homeOwner = null,
        public ?array $tourLocation = null,
        public ?array $tourDateStart = null,
        public ?array $tourDate = null,
        public ?array $tourResult = null,
        public ?string $qualificationStatus = null,
        public ?array $lastDispositions = null,
        public ?int $attemptCount = null,
    ) {}
}
