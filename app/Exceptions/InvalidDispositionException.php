<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidDispositionException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Disposition is not configured for this company.');
    }
}
