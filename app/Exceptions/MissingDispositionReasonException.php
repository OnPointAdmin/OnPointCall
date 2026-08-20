<?php

namespace App\Exceptions;

use RuntimeException;

class MissingDispositionReasonException extends RuntimeException
{
    public static function make(): self
    {
        return new self('A reason is required for this disposition.');
    }
}
