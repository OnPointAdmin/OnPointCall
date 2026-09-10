<?php

namespace App\Filament\Support;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\QualifiedPartnersMatch;

class LeadTableFilterMapper
{
    /**
     * @param  array<string, mixed>|null  $tableFilters
     */
    public static function toHoldingFilter(?array $tableFilters): HoldingFilter
    {
        $tableFilters ??= [];

        $leadType = self::selectValue($tableFilters, 'lead_type');
        $callingList = self::selectValue($tableFilters, 'calling_list_id');
        $sourceCallingListId = null;

        if ($callingList !== null && $callingList !== '' && $callingList !== 'holding') {
            $sourceCallingListId = (int) $callingList;
        }

        $importBatch = self::selectValue($tableFilters, 'import_batch_id');

        return new HoldingFilter(
            leadType: $leadType !== null && $leadType !== '' ? (string) $leadType : null,
            sourceCallingListId: $sourceCallingListId,
            state: self::selectValues($tableFilters, 'state'),
            venue: self::selectValues($tableFilters, 'venue'),
            event: self::selectValues($tableFilters, 'event'),
            importBatchId: $importBatch !== null && $importBatch !== '' ? (int) $importBatch : null,
            importedFrom: self::filterField($tableFilters, 'imported_at', 'start_date'),
            importedTo: self::filterField($tableFilters, 'imported_at', 'end_date'),
            createdFrom: self::filterField($tableFilters, 'created_at', 'start_date'),
            createdTo: self::filterField($tableFilters, 'created_at', 'end_date'),
            zip: self::filterField($tableFilters, 'zip'),
            partner: self::selectValues($tableFilters, 'partner'),
            fileName: self::filterField($tableFilters, 'file_name'),
            softScoreCode: self::selectValues($tableFilters, 'soft_score_code'),
            ageRange: self::selectValues($tableFilters, 'age_range'),
            annualIncome: self::selectValues($tableFilters, 'annual_income'),
            maritalStatus: self::selectValues($tableFilters, 'marital_status'),
            gender: self::selectValues($tableFilters, 'gender'),
            homeOwner: self::selectValues($tableFilters, 'home_owner'),
            tourLocation: self::selectValues($tableFilters, 'tour_location'),
            tourDateStart: self::selectValues($tableFilters, 'tour_date_start'),
            tourDate: self::selectValues($tableFilters, 'tour_date'),
            tourResult: self::selectValues($tableFilters, 'tour_result'),
            qualificationStatus: self::selectValue($tableFilters, 'qualification_status'),
            lastDispositions: self::selectValues($tableFilters, 'last_disposition'),
            attemptCount: self::attemptCount($tableFilters),
            qualifiedPartners: self::selectValues($tableFilters, 'qualified_partners'),
            qualifiedPartnersMatch: self::selectValue($tableFilters, 'qualified_partners_match')
                ?? QualifiedPartnersMatch::InList->value,
        );
    }

    /**
     * @param  array<string, mixed>  $tableFilters
     */
    private static function selectValue(array $tableFilters, string $name): ?string
    {
        $state = $tableFilters[$name] ?? null;

        if (! is_array($state)) {
            return null;
        }

        $value = $state['value'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $tableFilters
     * @return list<string>|null
     */
    private static function selectValues(array $tableFilters, string $name): ?array
    {
        $state = $tableFilters[$name] ?? null;

        if (! is_array($state)) {
            return null;
        }

        $values = $state['values'] ?? null;

        if ($values === null) {
            $value = $state['value'] ?? null;

            if ($value === null || $value === '') {
                return null;
            }

            $values = [$value];
        }

        if (! is_array($values)) {
            return null;
        }

        $normalized = array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $values),
            static fn (string $item): bool => $item !== '',
        ));

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @param  array<string, mixed>  $tableFilters
     */
    private static function filterField(array $tableFilters, string $name, ?string $field = null): ?string
    {
        $state = $tableFilters[$name] ?? null;

        if (! is_array($state)) {
            return null;
        }

        $key = $field ?? array_key_first($state);
        $value = $key !== null ? ($state[$key] ?? null) : null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $tableFilters
     */
    private static function attemptCount(array $tableFilters): ?int
    {
        $value = self::filterField($tableFilters, 'attempt_count', 'attempt_count');

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }
}
