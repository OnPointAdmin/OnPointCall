<?php

namespace App\Support;

class PasswordResetContext
{
    public const SurfaceAgent = 'agent';

    public const SurfaceAdmin = 'admin';

    private static string $surface = self::SurfaceAdmin;

    public static function useAgentSurface(): void
    {
        static::$surface = self::SurfaceAgent;
    }

    public static function useAdminSurface(): void
    {
        static::$surface = self::SurfaceAdmin;
    }

    public static function surface(): string
    {
        return static::$surface;
    }

    public static function reset(): void
    {
        static::$surface = self::SurfaceAdmin;
    }
}
