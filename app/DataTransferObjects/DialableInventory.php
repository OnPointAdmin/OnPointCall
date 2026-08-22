<?php

namespace App\DataTransferObjects;

readonly class DialableInventory
{
    public function __construct(
        public int $readyNow,
        public int $waiting,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0);
    }
}
