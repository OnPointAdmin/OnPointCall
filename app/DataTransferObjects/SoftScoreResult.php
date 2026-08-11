<?php

namespace App\DataTransferObjects;

use App\Enums\SoftScoreStatus;

readonly class SoftScoreResult
{
    public function __construct(
        public SoftScoreStatus $status,
        public ?string $qualificationCode = null,
        public ?string $error = null,
    ) {}
}
