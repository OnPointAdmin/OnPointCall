<?php

namespace App\DataTransferObjects;

readonly class DncPhoneResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $field,
        public string $phone,
        public ?string $resultCode,
        public ?string $reason,
        public ?string $suppress,
        public array $raw = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'phone' => $this->phone,
            'result_code' => $this->resultCode,
            'reason' => $this->reason,
            'suppress' => $this->suppress,
            'region' => $this->raw['RegionAbbrev'] ?? null,
            'country' => $this->raw['Country'] ?? null,
            'locale' => $this->raw['Locale'] ?? null,
            'carrier_info' => $this->raw['CarrierInfo'] ?? null,
            'line_type' => $this->raw['LineType'] ?? null,
        ];
    }
}
