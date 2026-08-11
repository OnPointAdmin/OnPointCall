<?php

namespace App\Exceptions;

use RuntimeException;

class CallbackOutsideWindowException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Callback time must fall inside the lead\'s legal calling window.');
    }
}
