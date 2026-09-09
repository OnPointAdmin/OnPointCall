<?php

namespace App\DataTransferObjects;

use App\Enums\BookingCheckStatus;

class BookingCheckResult
{
    /**
     * @param  list<array<string, mixed>>  $matches
     */
    public function __construct(
        public BookingCheckStatus $status,
        public array $matches = [],
        public ?string $error = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'matches' => $this->matches,
            'error' => $this->error,
        ];
    }
}
