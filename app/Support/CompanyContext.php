<?php

namespace App\Support;

class CompanyContext
{
    protected static ?int $companyId = null;

    public static function set(?int $companyId): void
    {
        static::$companyId = $companyId;
    }

    public static function get(): ?int
    {
        return static::$companyId;
    }

    public static function id(): ?int
    {
        return static::$companyId;
    }

    /**
     * Explicit context first, then the authenticated user's company.
     * Use this when assigning company_id on create. Do not use in CompanyScope —
     * that must stay on explicit context only to avoid auth recursion.
     */
    public static function idOrAuthenticated(): ?int
    {
        return static::$companyId ?? auth()->user()?->company_id;
    }

    public static function clear(): void
    {
        static::$companyId = null;
    }
}
