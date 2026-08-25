<?php

namespace App\Exceptions;

use RuntimeException;

class HoldingReleaseException extends RuntimeException
{
    public static function leadTypeMismatch(): self
    {
        return new self('Target calling list lead type must match the selected holding leads.');
    }

    public static function invalidCount(): self
    {
        return new self('Release count must be at least 1.');
    }

    public static function sameSourceAndTarget(): self
    {
        return new self('Source and target calling list must be different.');
    }
}
