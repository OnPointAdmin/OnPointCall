<?php

namespace App\DataTransferObjects;

use App\Enums\QualificationStatus;

readonly class QualificationResult
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>|null  $request
     */
    public function __construct(
        public QualificationStatus $status,
        public ?array $payload = null,
        public ?array $request = null,
        public ?string $error = null,
    ) {}
}
