<?php

namespace App\Support;

use App\Models\Lead;
use InvalidArgumentException;

class LeadDemographicOptions
{
    public const AGE_RANGES = [
        'Under 25',
        '25 - 27',
        '28 - 29',
        '30 - 39',
        '40 - 49',
        '50 - 59',
        '60+',
    ];

    public const INCOMES = [
        'Below $25k',
        '$25k - $49k',
        '$50k - $59k',
        '$60k - $74k',
        '$75k - $99k',
        '$100K +',
    ];

    public const MARITAL_STATUSES = [
        'Married',
        'Single',
        'Engaged',
        'Cohabitating',
    ];

    public const GENDERS = [
        'Male',
        'Female',
        'Other',
    ];

    public const HOMEOWNERS = [
        'Homeowner (0–3 years)',
        'Homeowner (3+ years)',
        'Currently Rent/Lease',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'age_range' => self::AGE_RANGES,
        'annual_income' => self::INCOMES,
        'marital_status' => self::MARITAL_STATUSES,
        'gender' => self::GENDERS,
        'home_owner' => self::HOMEOWNERS,
    ];

    /**
     * Canonical choices first, then any other values stored on company leads.
     *
     * @return list<string>
     */
    public static function for(string $column, int $companyId, ?string $current = null): array
    {
        if (! isset(self::COLUMNS[$column])) {
            throw new InvalidArgumentException("Unsupported demographic column: {$column}");
        }

        $canonical = self::COLUMNS[$column];
        $canonicalLookup = array_flip($canonical);
        $extras = [];

        $fromDb = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);

        foreach ($fromDb as $value) {
            $value = trim((string) $value);

            if ($value !== '' && ! isset($canonicalLookup[$value])) {
                $extras[$value] = $value;
            }
        }

        $current = $current !== null ? trim($current) : '';

        if ($current !== '' && ! isset($canonicalLookup[$current])) {
            $extras[$current] = $current;
        }

        ksort($extras);

        return array_values(array_merge($canonical, array_values($extras)));
    }
}
