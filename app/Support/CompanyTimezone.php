<?php

namespace App\Support;

use App\Models\AppSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

class CompanyTimezone
{
    public const DEFAULT = 'America/New_York';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'America/New_York' => 'Eastern (America/New_York)',
            'America/Chicago' => 'Central (America/Chicago)',
            'America/Denver' => 'Mountain (America/Denver)',
            'America/Phoenix' => 'Arizona (America/Phoenix)',
            'America/Los_Angeles' => 'Pacific (America/Los_Angeles)',
            'America/Anchorage' => 'Alaska (America/Anchorage)',
            'Pacific/Honolulu' => 'Hawaii (Pacific/Honolulu)',
        ];
    }

    public static function for(?int $companyId): string
    {
        if (! $companyId) {
            return self::DEFAULT;
        }

        $timezone = AppSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->value('dashboard_email_timezone');

        return self::normalize(is_string($timezone) ? $timezone : null);
    }

    public static function forAuthenticated(): string
    {
        $user = Auth::guard('agent')->user() ?? Auth::user();

        return self::for($user?->company_id);
    }

    public static function parse(string $datetime, ?int $companyId): Carbon
    {
        return Carbon::parse($datetime, self::for($companyId))->utc();
    }

    public static function format(?CarbonInterface $at, string $timezone, string $format = 'M j, g:i A T'): ?string
    {
        if ($at === null) {
            return null;
        }

        return $at->copy()->timezone($timezone)->format($format);
    }

    public static function display(mixed $at, ?int $companyId = null, string $format = 'M j, g:i A T'): ?string
    {
        if ($at === null || $at === '') {
            return null;
        }

        $carbon = $at instanceof CarbonInterface ? $at : Carbon::parse($at);
        $timezone = $companyId !== null ? self::for($companyId) : self::forAuthenticated();

        return self::format($carbon, $timezone, $format);
    }

    public static function normalize(?string $timezone): string
    {
        if (! is_string($timezone) || $timezone === '') {
            return self::DEFAULT;
        }

        return in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : self::DEFAULT;
    }
}
