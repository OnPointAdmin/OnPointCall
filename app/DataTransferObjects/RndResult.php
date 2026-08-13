<?php

namespace App\DataTransferObjects;

use App\Enums\RndStatus;

readonly class RndResult
{
    public function __construct(
        public RndStatus $status,
        public ?string $error = null,
    ) {}
}
