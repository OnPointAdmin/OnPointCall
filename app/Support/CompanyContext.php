<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

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
        return static::get() ?? Auth::user()?->company_id;
    }

    public static function clear(): void
    {
        static::$companyId = null;
    }
}
