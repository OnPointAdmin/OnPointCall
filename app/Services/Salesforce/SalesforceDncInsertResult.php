<?php

namespace App\Services\Salesforce;

class SalesforceDncInsertResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $id = null,
        public readonly ?string $error = null,
    ) {}

    public static function success(string $id): self
    {
        return new self(success: true, id: $id);
    }

    public static function failure(string $error): self
    {
        return new self(success: false, error: $error);
    }
}
