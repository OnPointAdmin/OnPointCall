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
     * @param  list<string>|null  $qualifiedPartners
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
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
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
        public ?array $qualifiedPartners = null,
        public ?string $qualifiedPartnersMatch = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lead_type' => $this->leadType,
            'source_calling_list_id' => $this->sourceCallingListId,
            'state' => $this->state,
            'venue' => $this->venue,
            'event' => $this->event,
            'import_batch_id' => $this->importBatchId,
            'imported_from' => $this->importedFrom,
            'imported_to' => $this->importedTo,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'zip' => $this->zip,
            'partner' => $this->partner,
            'file_name' => $this->fileName,
            'soft_score_code' => $this->softScoreCode,
            'age_range' => $this->ageRange,
            'annual_income' => $this->annualIncome,
            'marital_status' => $this->maritalStatus,
            'gender' => $this->gender,
            'home_owner' => $this->homeOwner,
            'tour_location' => $this->tourLocation,
            'tour_date_start' => $this->tourDateStart,
            'tour_date' => $this->tourDate,
            'tour_result' => $this->tourResult,
            'qualification_status' => $this->qualificationStatus,
            'last_dispositions' => $this->lastDispositions,
            'attempt_count' => $this->attemptCount,
            'qualified_partners' => $this->qualifiedPartners,
            'qualified_partners_match' => $this->qualifiedPartnersMatch,
        ];
    }
}
