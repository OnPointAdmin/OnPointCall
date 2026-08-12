<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Agent => 'Agent',
        };
    }

    public static function coerce(self|string $value): self
    {
        return $value instanceof self ? $value : self::from($value);
    }

    public function canAccessAdmin(): bool
    {
        return $this === self::Admin || $this === self::Manager;
    }
}
