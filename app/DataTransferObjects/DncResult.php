<?php

namespace App\DataTransferObjects;

use App\Enums\DncStatus;

readonly class DncResult
{
    /**
     * @param  array<string, DncPhoneResult>  $phones
     * @param  list<string>  $ignoredReasons
     */
    public function __construct(
        public DncStatus $status,
        public ?string $error = null,
        public array $phones = [],
        public ?string $hitReason = null,
        public bool $ignoreNationalDnc = false,
        public array $ignoredReasons = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $phones = [];

        foreach ($this->phones as $field => $phone) {
            $phones[$field] = $phone->toArray();
        }

        return [
            'status' => $this->status->value,
            'hit_reason' => $this->hitReason,
            'ignore_national_dnc' => $this->ignoreNationalDnc,
            'ignored_reasons' => $this->ignoredReasons,
            'phones' => $phones,
        ];
    }
}
