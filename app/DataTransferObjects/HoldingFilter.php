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
     * @param  list<string>|null  $creditCardType
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
        public ?array $creditCardType = null,
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
            'credit_card_type' => $this->creditCardType,
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

    /**
     * @param  list<string>  $keys  Table filter names to clear for dependent-option queries.
     */
    public function withoutFields(string ...$keys): self
    {
        $exclude = array_fill_keys($keys, true);

        $clearPartners = isset($exclude['qualified_partners']);

        return new self(
            leadType: isset($exclude['lead_type']) ? null : $this->leadType,
            sourceCallingListId: isset($exclude['calling_list_id']) ? null : $this->sourceCallingListId,
            state: isset($exclude['state']) ? null : $this->state,
            venue: isset($exclude['venue']) ? null : $this->venue,
            event: isset($exclude['event']) ? null : $this->event,
            importBatchId: isset($exclude['import_batch_id']) ? null : $this->importBatchId,
            importedFrom: isset($exclude['imported_at']) ? null : $this->importedFrom,
            importedTo: isset($exclude['imported_at']) ? null : $this->importedTo,
            createdFrom: isset($exclude['created_at']) ? null : $this->createdFrom,
            createdTo: isset($exclude['created_at']) ? null : $this->createdTo,
            zip: isset($exclude['zip']) ? null : $this->zip,
            partner: isset($exclude['partner']) ? null : $this->partner,
            fileName: isset($exclude['file_name']) ? null : $this->fileName,
            softScoreCode: isset($exclude['soft_score_code']) ? null : $this->softScoreCode,
            ageRange: isset($exclude['age_range']) ? null : $this->ageRange,
            annualIncome: isset($exclude['annual_income']) ? null : $this->annualIncome,
            maritalStatus: isset($exclude['marital_status']) ? null : $this->maritalStatus,
            gender: isset($exclude['gender']) ? null : $this->gender,
            homeOwner: isset($exclude['home_owner']) ? null : $this->homeOwner,
            creditCardType: isset($exclude['credit_card_type']) ? null : $this->creditCardType,
            tourLocation: isset($exclude['tour_location']) ? null : $this->tourLocation,
            tourDateStart: isset($exclude['tour_date_start']) ? null : $this->tourDateStart,
            tourDate: isset($exclude['tour_date']) ? null : $this->tourDate,
            tourResult: isset($exclude['tour_result']) ? null : $this->tourResult,
            qualificationStatus: isset($exclude['qualification_status']) ? null : $this->qualificationStatus,
            lastDispositions: isset($exclude['last_disposition']) ? null : $this->lastDispositions,
            attemptCount: isset($exclude['attempt_count']) ? null : $this->attemptCount,
            qualifiedPartners: $clearPartners ? null : $this->qualifiedPartners,
            qualifiedPartnersMatch: $clearPartners ? null : $this->qualifiedPartnersMatch,
        );
    }

    public function withSource(?int $sourceCallingListId): self
    {
        return new self(
            leadType: $this->leadType,
            sourceCallingListId: $sourceCallingListId,
            state: $this->state,
            venue: $this->venue,
            event: $this->event,
            importBatchId: $this->importBatchId,
            importedFrom: $this->importedFrom,
            importedTo: $this->importedTo,
            createdFrom: $this->createdFrom,
            createdTo: $this->createdTo,
            zip: $this->zip,
            partner: $this->partner,
            fileName: $this->fileName,
            softScoreCode: $this->softScoreCode,
            ageRange: $this->ageRange,
            annualIncome: $this->annualIncome,
            maritalStatus: $this->maritalStatus,
            gender: $this->gender,
            homeOwner: $this->homeOwner,
            creditCardType: $this->creditCardType,
            tourLocation: $this->tourLocation,
            tourDateStart: $this->tourDateStart,
            tourDate: $this->tourDate,
            tourResult: $this->tourResult,
            qualificationStatus: $this->qualificationStatus,
            lastDispositions: $this->lastDispositions,
            attemptCount: $this->attemptCount,
            qualifiedPartners: $this->qualifiedPartners,
            qualifiedPartnersMatch: $this->qualifiedPartnersMatch,
        );
    }
}
